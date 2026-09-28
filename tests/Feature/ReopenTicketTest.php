<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketReopenedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Services\TicketReopening;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    Queue::fake();
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->supporter = User::factory()->create(['name' => 'Test Admin']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->supporter->id, $this->colleague->id]));
});

function closedTicket(array $attributes = [], int $daysAgo = 1): Ticket
{
    $ticket = Ticket::factory()->open()->create([
        'submitter_id' => test()->requester->id,
        'assignee_id' => test()->supporter->id,
        ...$attributes,
    ]);
    $ticket->close(dispositionId: TicketDisposition::factory()->create(['panel' => $ticket->panel])->id, closedById: test()->supporter->id);
    $ticket->forceFill(['closed_at' => now()->subDays($daysAgo)])->saveQuietly();

    return $ticket->refresh();
}

it('reopens a ticket at its panel\'s first status, clearing the close and saying who reopened it', function () {
    Event::fake([TicketReopenedEvent::class]);
    $ticket = closedTicket();
    $this->actingAs($this->requester);

    $ticket->reopen();

    $ticket->refresh();
    expect($ticket->isClosed)->toBeFalse()
        ->and($ticket->closed_by)->toBeNull()
        ->and($ticket->disposition_id)->toBeNull()
        ->and($ticket->status_id)->toBe(TicketStatus::getOpenStatusFor($ticket)->getKey())
        ->and($ticket->ticketActivities()->where('type', ActivityType::Reopened)->sole()->content)->toBe('Conversation reopened by Aisha Brooks');

    Event::assertDispatched(TicketReopenedEvent::class, fn (TicketReopenedEvent $event): bool => $event->ticket->is($ticket) && $event->actor->is($this->requester));

    $ticket->reopen();
    Event::assertDispatchedTimes(TicketReopenedEvent::class, 1);
});

it('lets a requester reopen their ticket only within the panel\'s reopen window', function (?int $windowDays, int $closedDaysAgo, bool $expected) {
    if ($windowDays !== null) {
        TicketPlugin::get()->reopenWindowDays($windowDays);
    }

    expect(TicketPlugin::get()->getReopenWindowDays())->toBe($windowDays ?? 30)
        ->and(Gate::forUser($this->requester)->allows('reopen', closedTicket(daysAgo: $closedDaysAgo)))->toBe($expected);
})->with([
    'closed yesterday, default window' => [null, 1, true],
    'closed 29 days ago, default window' => [null, 29, true],
    'closed 31 days ago, default window' => [null, 31, false],
    'closed 8 days ago, a 7-day window' => [7, 8, false],
]);

it('lets supporters of the ticket\'s panel and an escalation\'s handler reopen it whenever it closed, and nobody reopen an open ticket', function () {
    $longClosed = closedTicket(daysAgo: 90);
    $escalation = escalationFrom(attributes: ['submitter_id' => $this->supporter->id]);
    $escalation->close(closedById: $this->colleague->id);
    $escalation->forceFill(['closed_at' => now()->subDays(90)])->saveQuietly();
    $stranger = User::factory()->create();

    expect(Gate::forUser($this->colleague)->allows('reopen', $longClosed))->toBeTrue()
        ->and(Gate::forUser($stranger)->allows('reopen', $longClosed))->toBeFalse()
        ->and(Gate::forUser($this->supporter)->allows('reopen', $escalation->refresh()))->toBeTrue()
        ->and(Gate::forUser($this->colleague)->allows('reopen', Ticket::factory()->open()->create()))->toBeFalse();
});

it('offers the requester a reopen or a new ticket within the window, only a new ticket after it, and staff only a reopen', function () {
    $recent = closedTicket();
    $old = closedTicket(daysAgo: 45);
    $reopening = resolve(TicketReopening::class);

    expect($reopening->choicesFor($recent, $this->requester))->toBe(['reopen', 'new'])
        ->and($reopening->choicesFor($old, $this->requester))->toBe(['new'])
        ->and($reopening->choicesFor($old, $this->colleague))->toBe(['reopen'])
        ->and($reopening->choicesFor(Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]), $this->requester))->toBe([]);
});

it('tells the chat when and how its ticket closed, and what the reader may do about it', function () {
    $ticket = closedTicket();
    $this->actingAs($this->requester);

    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk()
        ->assertJsonPath('ticket.closed_at', $ticket->closed_at->toIso8601String())
        ->assertJsonPath('ticket.reopen_choices', ['reopen', 'new'])
        ->assertJsonPath('ticket.reopen_window_days', 30);
});

it('reopens and posts a reply only when asked to and allowed to', function (Closure $ticket, Closure $writer, bool $reopen, bool $reopened) {
    $record = $ticket();
    $this->actingAs($writer());

    $response = $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $record]), [
        'content' => '<p>It is broken again.</p>',
        'reopen' => $reopen,
    ]);

    $reopened ? $response->assertOk() : $response->assertUnprocessable();

    expect($record->refresh()->isClosed)->toBe(! $reopened)
        ->and($record->ticketActivities()->where('type', ActivityType::Message)->where('content', 'like', '%broken again%')->exists())->toBe($reopened);
})->with([
    'the requester, within the window, asking' => [fn () => closedTicket(), fn () => test()->requester, true, true],
    'the requester, within the window, not asking' => [fn () => closedTicket(), fn () => test()->requester, false, false],
    'the requester, after the window' => [fn () => closedTicket(daysAgo: 45), fn () => test()->requester, true, false],
    'a supporter, long after it closed' => [fn () => closedTicket(daysAgo: 90), fn () => test()->colleague, true, true],
]);

it('starts a new ticket that follows up a closed one, linking back where its reader opens it', function () {
    $earlier = closedTicket(daysAgo: 45);
    $this->actingAs($this->requester);

    $id = $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Rent still wrong', 'follows_up' => $earlier->id])
        ->assertSuccessful()
        ->json('id');

    $note = Ticket::query()->find($id)->ticketActivities()->where('type', ActivityType::FollowsUp)->sole();

    expect($note->sender)->toBe(ActivitySender::System)
        ->and($note->content)->toBe('Follows up on <a href="'.e(url('/').'#ticket-'.$earlier->id).'">#'.$earlier->id.'</a>')
        ->and(resolve(TicketReopening::class)->choicesFor($earlier, $this->requester))->toBe(['new']);

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $id]), ['content' => '<p>Rent still wrong</p>'])->assertOk();

    expect(Ticket::query()->find($id)->ticketActivities()->where('type', ActivityType::Message)->where('sender', ActivitySender::System)->count())
        ->toBe(2, 'the widget\'s intro and auto response still open the new ticket');
});

it('refuses a follow-up of a ticket that is open or someone else\'s', function (Closure $earlier) {
    $this->actingAs($this->requester);

    $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Rent still wrong', 'follows_up' => $earlier()->id])
        ->assertForbidden();
})->with([
    'open' => [fn () => Ticket::factory()->open()->create(['submitter_id' => test()->requester->id])],
    'someone else\'s' => [fn () => closedTicket(['submitter_id' => User::factory()->create()->id])],
]);

it('tells the assignee, once, who reopened the ticket and what they wrote', function (bool $reopenedNoticeFirst) {
    $ticket = closedTicket();
    $this->actingAs($this->requester);
    $ticket->reopen();
    $ticket->ticketActivities()->create(['type' => ActivityType::Message, 'sender' => ActivitySender::User, 'user_id' => $this->requester->id, 'content' => '<p>It is broken again.</p>']);

    $notifications = [
        new TicketNotification($ticket, new TicketReopenedEvent($ticket, $this->requester)),
        new TicketNotification($ticket, new TicketActivityEvent($ticket, ActivityType::Message, null, $this->requester)),
    ];
    $sent = [];

    foreach ($reopenedNoticeFirst ? $notifications : array_reverse($notifications) as $notification) {
        if ($notification->shouldSend($this->supporter)) {
            $mail = $notification->toMail($this->supporter);
            $sent[] = [$mail->subject, $mail->viewData['intro']];
        }
    }

    expect($sent)->toBe([["Ticket reopened #{$ticket->id} – {$ticket->subject}", 'Aisha Brooks reopened this ticket.']])
        ->and((new TicketNotification($ticket, new TicketReopenedEvent($ticket, $this->requester)))->shouldSend($this->requester))->toBeFalse();
})->with(['the reopen notice first' => true, 'the reply notice first' => false]);

it('leaves a reopened original\'s closed escalation closed, and puts a reopened escalation\'s originals back under it', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $links = resolve(TicketEscalationLinks::class);
    $escalation = escalationFrom(attributes: ['submitter_id' => $this->supporter->id]);
    $original = closedTicket(['linked_ticket_id' => $escalation->id]);
    $escalation->close(closedById: $this->colleague->id);
    $this->actingAs($this->supporter);

    $original->reopen();

    expect($links->hasClosedEscalation($original->refresh()))->toBeTrue()
        ->and($links->hasOpenEscalation($original))->toBeFalse();

    $escalation->refresh()->reopen();

    expect($links->hasOpenEscalation($original->refresh()))->toBeTrue();
});
