<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Livewire\CopilotTicketPanel;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\Users\DeactivatedUser;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();

    $this->user = $this->login(User::factory()->create(['name' => 'Alex Kim']));
    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->user->id]);
});

function disableRepliesOn(string $panel, string $reason = 'You are only viewing this ticket.'): void
{
    TicketPlugin::get($panel)->replyDisabledUsing(fn (Ticket $ticket, User $user): ?string => $user->name === 'Alex Kim' ? $reason : null);
}

function messageCount(Ticket $ticket): int
{
    return $ticket->ticketActivities()->where('type', ActivityType::Message)->count();
}

it('lets everyone reply when the host sets no rule', function () {
    expect($this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))->json('ticket.reply_disabled_reason'))->toBeNull();

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])->assertOk();
});

it('gives the chat the host\'s reason with the ticket', function () {
    disableRepliesOn('test');

    expect($this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))->json('ticket.reply_disabled_reason'))
        ->toBe('You are only viewing this ticket.');
});

it('refuses a message with the host\'s reason under message', function () {
    disableRepliesOn('test');

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'You are only viewing this ticket.']);

    expect(messageCount($this->ticket))->toBe(0);
});

it('refuses an attachment upload the same way', function () {
    disableRepliesOn('test');

    $this->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => $this->ticket]), [
        'filename' => 'scan.jpg',
        'content_type' => 'image/jpeg',
        'content_length' => 1024,
    ])->assertForbidden()->assertJson(['message' => 'You are only viewing this ticket.']);
});

it('refuses before a reply would reopen a closed ticket', function () {
    disableRepliesOn('test');
    $closed = Ticket::factory()->closed()->create(['submitter_id' => $this->user->id]);

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $closed]), ['content' => '<p>Again</p>', 'reopen' => true])
        ->assertForbidden();

    expect($closed->refresh()->isClosed)->toBeTrue();
});

it('asks only about the person writing, given the ticket and the user', function () {
    $asked = [];
    TicketPlugin::get()->replyDisabledUsing(function (Ticket $ticket, User $user) use (&$asked): ?string {
        $asked[] = [$ticket->id, $user->id];

        return null;
    });

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])->assertOk();

    expect($asked)->toContain([$this->ticket->id, $this->user->id]);

    disableRepliesOn('test');
    $this->actingAs(User::factory()->create(['name' => 'Maria Lopez']));

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])->assertOk();
});

it('asks the panel the chat was opened in, or else the ticket\'s', function () {
    disableRepliesOn('test2', 'Read only in test2.');

    $reason = fn (array $headers = []) => $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]), $headers)->json('ticket.reply_disabled_reason');

    expect($reason(['X-Padmission-Tickets-Panel' => 'test2']))->toBe('Read only in test2.')
        ->and($reason(['X-Padmission-Tickets-Panel' => 'test']))->toBeNull();

    $other = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => $this->user->id]);
    Filament::setCurrentPanel(null);

    expect($this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $other]))->json('ticket.reply_disabled_reason'))->toBe('Read only in test2.');
});

it('draws the ticket page\'s reply box disabled with the reason, rather than hiding it', function () {
    disableRepliesOn('test');
    $supporter = User::factory()->create(['name' => 'Alex Kim']);
    $ticket = Ticket::factory()->open()->create();

    $this->actingAs($supporter);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSeeHtml('can-reply="true"')
        ->assertSeeHtml('reply-disabled-reason="You are only viewing this ticket."');

    TicketPlugin::get()->replyDisabledUsing(null);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSeeHtml('reply-disabled-reason=""');
});

describe('Every path that writes in the chat', function () {
    it('asks the ticket\'s own panel too, so the chat cannot name another panel to get past it', function () {
        disableRepliesOn('test');

        $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'], ['X-Padmission-Tickets-Panel' => 'test2'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'You are only viewing this ticket.']);

        $this->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => $this->ticket]), [
            'filename' => 'scan.jpg',
            'content_type' => 'image/jpeg',
            'content_length' => 1024,
        ], ['X-Padmission-Tickets-Panel' => 'panel-test2'])->assertForbidden();

        expect(messageCount($this->ticket))->toBe(0);
    });

    it('ignores a panel the chat names that the user may not enter', function () {
        disableRepliesOn('test2', 'Read only in test2.');
        $outsider = DeactivatedUser::query()->findOrFail(User::factory()->create(['name' => 'Alex Kim'])->id);
        $ticket = Ticket::factory()->open()->create(['submitter_id' => $outsider->id]);
        $this->actingAs($outsider);

        expect($this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]), ['X-Padmission-Tickets-Panel' => 'test2'])->json('ticket.reply_disabled_reason'))
            ->toBeNull();
    });

    it('refuses a new ticket from someone kept from writing', function () {
        disableRepliesOn('test');

        $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Rent is wrong'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'You are only viewing this ticket.']);

        expect(Ticket::query()->where('subject', 'Rent is wrong')->exists())->toBeFalse();
    });

    it('refuses a follow-up on a ticket they are kept from writing on', function () {
        $closed = Ticket::factory()->closed()->create(['submitter_id' => $this->user->id]);
        TicketPlugin::get()->replyDisabledUsing(fn (Ticket $ticket): ?string => $ticket->is($closed) ? 'That ticket is archived.' : null);

        $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Rent still wrong', 'follows_up' => $closed->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'That ticket is archived.']);

        expect(Ticket::query()->where('subject', 'Rent still wrong')->exists())->toBeFalse();
    });

    it('does not offer Escalate\'s message to the requester, nor send one, to someone kept from writing', function () {
        $requester = User::factory()->create(['name' => 'Aisha Brooks']);
        $original = Ticket::factory()->open()->create(['submitter_id' => $requester->id, 'turn' => Turn::Supporter]);
        TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);
        disableRepliesOn('test');

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->mountAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'))
            ->fillForm([
                'subject' => 'Rent is wrong',
                'message' => tiptapDocument('Please check'),
                'notify_requester' => true,
                'requester_message' => '<p>On it</p>',
            ])
            ->callMountedAction();

        expect(messageCount($original))->toBe(0);
    });

    it('does not let someone kept from writing resolve their ticket from the assistant', function () {
        (new TicketStatusSeeder)->run();
        disableRepliesOn('test');

        Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
            ->assertDontSee('Resolve this ticket?')
            ->call('resolveTicket')
            ->assertNotified('You are only viewing this ticket.');

        expect($this->ticket->refresh()->isClosed)->toBeFalse();
    });
});
