<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Support\ConversationState;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->owner = User::factory()->create(['name' => 'Test Admin']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->padmission = User::factory()->create(['name' => 'Kevin McKee']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->owner->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Padmission');
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->padmission->id));

    $this->escalation = Ticket::factory()->open()->create([
        'panel' => 'test2',
        'source_panel' => 'test',
        'submitter_id' => $this->owner->id,
        'assignee_id' => $this->padmission->id,
        'turn' => Turn::User,
    ]);
    $this->original = Ticket::factory()->open()->create([
        'linked_ticket_id' => $this->escalation->id,
        'submitter_id' => $this->requester->id,
        'assignee_id' => $this->owner->id,
        'turn' => Turn::Supporter,
    ]);
});

function writeMessage(Ticket $ticket, ActivitySender $sender, User $author, string $content): void
{
    $ticket->ticketActivities()->create(['type' => ActivityType::Message, 'sender' => $sender, 'user_id' => $author->id, 'content' => $content]);
}

function padmissionReplied(Ticket $escalation, User $padmission): void
{
    writeMessage($escalation, ActivitySender::Supporter, $padmission, 'It is fixed.');
}

it('tells the owner to pass on a reply that came before the escalation closed, not to answer there', function () {
    writeMessage($this->original, ActivitySender::Supporter, $this->owner, 'Looking into it.');
    padmissionReplied($this->escalation, $this->padmission);
    $this->escalation->close(closedById: $this->padmission->id);
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertSee('Kevin McKee closed the escalation')
        ->assertSee('Padmission replied before the escalation was closed. Pass it on to Aisha Brooks here.')
        ->assertDontSee('answer Padmission there');

    expect(ConversationState::for($this->original->refresh()->load('parentTicket')))
        ->markerTooltip()->toBe('Padmission replied before the escalation was closed. Pass the answer on to Aisha Brooks on their ticket.');
});

it('offers Read the reply only while the escalation is not beside the chat, and Open the escalation once', function () {
    writeMessage($this->original, ActivitySender::Supporter, $this->owner, 'Looking into it.');
    padmissionReplied($this->escalation, $this->padmission);
    $this->login($this->owner);

    $page = Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertSet('linkedTicketId', $this->escalation->id)
        ->assertDontSee('Read Padmission&#039;s reply', escape: false)
        ->call('closeLinked')
        ->assertSee('Read Padmission&#039;s reply', escape: false);

    expect(substr_count($page->html(), 'Open the escalation'))->toBe(1);
});

it('keeps Take over in the status line to people who may see the escalation, on open originals', function () {
    padmissionReplied($this->escalation, $this->padmission);
    $own = Ticket::factory()->open()->create([
        'linked_ticket_id' => $this->escalation->id,
        'submitter_id' => $this->colleague->id,
        'assignee_id' => $this->owner->id,
    ]);
    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $own->id])
        ->assertActionHidden('takeOver');

    $this->original->close(closedById: $this->owner->id);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertActionHidden('takeOver');
});

it('puts an assignee on hold while a colleague owes the relay, and names who the reply went to', function () {
    $this->original->update(['assignee_id' => $this->colleague->id]);
    writeMessage($this->original, ActivitySender::Supporter, $this->colleague, 'Looking into it.');
    padmissionReplied($this->escalation, $this->padmission);
    $this->login($this->colleague);

    $row = Ticket::query()->withConversationState()->find($this->original->id);
    $state = ConversationState::fromRow($row->load('parentTicket.submitter'));

    expect($row->conversation_waiting_on)->toBe('you_on_hold')
        ->and((int) $row->conversation_rank)->toBe(1)
        ->and($state->color())->toBe('gray')
        ->and($state->markerLabel())->toBe('Padmission replied to Test Admin')
        ->and($state->markerColor())->toBe('gray');

    writeMessage($this->original, ActivitySender::User, $this->requester, 'Any news?');

    expect(Ticket::query()->withConversationState()->find($this->original->id)->conversation_waiting_on)->toBe('you');
});

it('calls the viewer You in the pane: on the original\'s card and on their own messages', function () {
    writeMessage($this->escalation, ActivitySender::User, $this->owner, 'Can you check the allowance table?');
    padmissionReplied($this->escalation, $this->padmission);
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->call('showLinked', $this->escalation->id)
        ->assertSeeHtml('<strong>You</strong>')
        ->assertDontSeeHtml('<strong>Test Admin</strong>')
        ->assertSeeHtml('<strong>Kevin McKee</strong>');

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->call('showLinked', $this->original->id)
        ->assertSeeHtml('<dd>You</dd>');
});

it('calls the viewer You under Handled by when adding to an escalation', function () {
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->owner->id]);
    $this->escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System);
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(TestAction::make(AddToEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'))
        ->assertMountedActionModalSee("#{$this->escalation->id}")
        ->assertMountedActionModalDontSee('Test Admin');
});

it('shows no escalation text on a support user\'s own ticket filed into another panel', function () {
    $widgetTicket = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->owner->id]);
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $widgetTicket->id])
        ->assertDontSee('Your team&#039;s conversation with Padmission', escape: false)
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
});

it('labels the Waiting on pill beside the pane and titles the tab with the subject', function () {
    padmissionReplied($this->escalation, $this->padmission);
    $this->login($this->owner);

    $page = Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertSet('linkedTicketId', $this->escalation->id)
        ->assertSee('Waiting on: You');

    expect($page->instance()->getTitle())->toBe($this->original->subject);
});

it('names the contact in lowercase mid-sentence when their name is unknown', function () {
    $this->escalation->update(['submitter_id' => null]);
    Filament::setCurrentPanel('test2');
    $this->login($this->padmission);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertSee('the contact passes your answers on')
        ->assertDontSee('Contact passes your answers on');
});
