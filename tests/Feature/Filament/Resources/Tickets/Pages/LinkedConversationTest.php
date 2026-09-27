<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->login();
});

function originalWithMessage(Ticket $escalation, string $message): Ticket
{
    $requester = User::factory()->create(['name' => 'Rita '.md5($message)]);
    $original = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id, 'submitter_id' => $requester->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $original->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'user_id' => $requester->id,
        'content' => "<p>{$message}</p>",
    ]);

    return $original;
}

function linkedMessage(Ticket $ticket, ActivitySender $sender, ?int $userId, string $content = 'A message'): TicketActivity
{
    return TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => $sender,
        'user_id' => $userId,
        'content' => "<p>{$content}</p>",
    ]);
}

describe('on an escalation sent to this panel', function () {
    beforeEach(function () {
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    });

    it('opens the first original beside the reply box and says who the conversation is with', function () {
        TicketPlugin::get()->describeTicketOriginUsing(fn (): string => 'Acme Housing');

        $contact = User::factory()->create(['name' => 'Test Admin']);
        $escalation = Ticket::factory()->open()->create(['submitter_id' => $contact->id]);
        $original = originalWithMessage($escalation, 'The rent looks wrong');
        $requester = 'Rita '.md5('The rent looks wrong');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSet('linkedTicketId', $original->id)
            ->assertSee(['The rent looks wrong', $requester])
            ->assertSee('Conversation with Test Admin, Acme Housing')
            ->assertSee("About {$requester}'s ticket. {$requester} never sees this conversation; Test Admin passes your answers on.")
            ->assertSee("{$requester}'s original ticket")
            ->assertSee(__('padmission-tickets::tickets.linked_view.read_only'))
            ->assertDontSeeHtml('pad-ti-linked__header-link')
            ->assertDontSeeHtml('pad-ti-transcript__title')
            ->assertActionHasLabel('show-linked', 'Hide linked ticket')
            ->callAction('show-linked')
            ->assertSet('linkedTicketId', null)
            ->assertActionHasLabel('show-linked', 'Show linked ticket');
    });

    it('switches between several originals and closes again', function () {
        $escalation = Ticket::factory()->open()->create();
        $first = originalWithMessage($escalation, 'First original message');
        $second = originalWithMessage($escalation, 'Second original message');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->call('showLinked', $second->id)
            ->assertSeeHtml('<span class="pad-ti-linked__tab-number">· #'.$first->id.'</span>')
            ->assertSeeHtml('<span class="pad-ti-linked__tab-number">· #'.$second->id.'</span>')
            ->assertSeeHtml("content: '{$second->subject}'")
            ->assertSee('Second original message')
            ->assertDontSee('First original message')
            ->call('showLinked', $first->id)
            ->assertSee('First original message')
            ->call('closeLinked')
            ->assertSet('linkedTicketId', null)
            ->assertDontSee('First original message');
    });

    it('names the contact alone when it has no organization', function () {
        $escalation = Ticket::factory()->open()->create(['submitter_id' => User::factory()->create(['name' => 'Test Admin'])->id]);
        originalWithMessage($escalation, 'No organization');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee('Conversation with Test Admin')
            ->assertDontSee('Conversation with Test Admin,');
    });

    it('ignores a ticket that is not linked to this one', function () {
        $escalation = Ticket::factory()->open()->create();
        originalWithMessage($escalation, 'Linked');
        $stranger = Ticket::factory()->create(['panel' => 'test2']);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->call('showLinked', $stranger->id)
            ->assertSet('linkedTicketId', null);
    });

    it('opens the original named in the address and drops one that is not linked', function () {
        $escalation = Ticket::factory()->open()->create();
        originalWithMessage($escalation, 'First');
        $second = originalWithMessage($escalation, 'Second');
        $stranger = Ticket::factory()->create(['panel' => 'test2']);

        Livewire::withQueryParams(['linked' => $second->id])
            ->test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSet('linkedTicketId', $second->id)
            ->assertSee('Second');

        Livewire::withQueryParams(['linked' => $stranger->id])
            ->test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSet('linkedTicketId', null);
    });

    it('offers a drawer instead when the panel asks for one', function () {
        TicketPlugin::get()->linkedConversationView(TicketPlugin::LINKED_VIEW_DRAWER);

        $escalation = Ticket::factory()->open()->create();
        originalWithMessage($escalation, 'Drawer message');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee('Drawer message')
            ->assertSeeHtml('pad-ti-linked--drawer');
    });

    it('opens nothing when the panel reads originals in a modal', function () {
        TicketPlugin::get()->linkedConversationView(TicketPlugin::LINKED_VIEW_MODAL);

        $escalation = Ticket::factory()->open()->create();
        $original = originalWithMessage($escalation, 'Modal message');

        Livewire::withQueryParams(['linked' => $original->id])
            ->test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSet('linkedTicketId', null);
    });
});

describe('on the side that escalated', function () {
    beforeEach(function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('shows the escalation thread beside an original and links to it', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        linkedMessage($escalation, ActivitySender::Supporter, null, 'Reply from the escalation team');
        $original = Ticket::factory()->open()->create([
            'linked_ticket_id' => $escalation->id,
            'submitter_id' => User::factory()->create(['name' => 'Rita Requester'])->id,
        ]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSet('linkedTicketId', null)
            ->assertSee('Conversation with Rita Requester')
            ->callAction('show-linked')
            ->assertSee('Reply from the escalation team')
            ->assertSee('Escalation to Platform Support')
            ->assertSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $escalation, 'linked' => $original->id])).'"')
            ->assertSee(__('padmission-tickets::tickets.linked_view.open_escalation'))
            ->assertActionHasLabel('show-linked', 'Hide linked ticket');
    });

    it('shows its originals on the escalation page, each linking to its own page', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => auth()->id()]);
        $original = Ticket::factory()->open()->create([
            'linked_ticket_id' => $escalation->id,
            'submitter_id' => User::factory()->create(['name' => 'Aisha Brooks'])->id,
        ]);
        linkedMessage($original, ActivitySender::User, $original->submitter_id, 'The rent is too high');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSet('linkedTicketId', null)
            ->assertSee('Conversation with Platform Support')
            ->assertSee("About Aisha Brooks's ticket. Aisha Brooks never sees this conversation.")
            ->assertActionHasLabel('show-linked', 'Show linked ticket')
            ->callAction('show-linked')
            ->assertSet('linkedTicketId', $original->id)
            ->assertSee(['The rent is too high', "Aisha Brooks's original ticket"])
            ->assertSee('Answer Aisha Brooks')
            ->assertSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $original, 'linked' => $escalation->id])).'"');
    });

    it('opens the escalation for its owner when the other team replied, and marks it seen', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => auth()->id(), 'turn' => Turn::User]);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id, 'turn' => Turn::Supporter]);
        linkedMessage($original, ActivitySender::User, $original->submitter_id);
        linkedMessage($escalation, ActivitySender::User, auth()->id());
        $reply = linkedMessage($escalation, ActivitySender::Supporter, null, 'Which household?');

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSet('linkedTicketId', $escalation->id)
            ->assertSee('Which household?');

        expect(TicketUserState::query()->where('ticket_id', $escalation->id)->where('user_id', auth()->id())->value('last_seen_activity_id'))
            ->toBe($reply->id);
    });

    it('leaves the escalation closed when there is no reply to pass on, or the viewer does not own it', function (bool $replied, bool $owner) {
        $colleague = User::factory()->create();
        $escalation = Ticket::factory()->open()->create([
            'panel' => 'test2',
            'source_panel' => 'test',
            'submitter_id' => $owner ? auth()->id() : $colleague->id,
            'turn' => Turn::Supporter,
        ]);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id, 'turn' => Turn::Supporter]);
        linkedMessage($escalation, ActivitySender::User, $escalation->submitter_id);

        if ($replied) {
            linkedMessage($escalation, ActivitySender::Supporter, null);
        }

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSet('linkedTicketId', null);
    })->with([
        'no reply, owner' => [false, true],
        'reply, colleague' => [true, false],
    ]);

    it('marks the escalation seen only for its owner', function () {
        $colleague = User::factory()->create();
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => $colleague->id]);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);
        linkedMessage($escalation, ActivitySender::Supporter, null);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->call('showLinked', $escalation->id)
            ->assertSet('linkedTicketId', $escalation->id);

        expect(TicketUserState::query()->where('ticket_id', $escalation->id)->where('user_id', auth()->id())->value('last_seen_activity_id'))->toBeNull();

        $this->actingAs($colleague);
        $reply = linkedMessage($escalation, ActivitySender::Supporter, null);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->call('showLinked', $escalation->id);

        expect(TicketUserState::query()->where('ticket_id', $escalation->id)->where('user_id', $colleague->id)->value('last_seen_activity_id'))
            ->toBe($reply->id);
    });

    it('shows the Waiting on badge above the chat while the pane hides the details', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => auth()->id(), 'turn' => Turn::Supporter]);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertDontSeeHtml('fi-section-header-after-ctn')
            ->call('showLinked', $original->id)
            ->assertSeeHtml('fi-section-header-after-ctn')
            ->assertSee('Platform Support owes the next reply.');
    });
})->after(fn () => Filament::setCurrentPanel('test'));

it('has nothing to show on a ticket without linked tickets', function () {
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden('show-linked');
});
