<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\Services\CopilotTicketService;
use Padmission\Tickets\Tests\User;

test('marking a ticket seen records the latest support response without changing notification state', function () {
    Event::fake();

    $user = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $user->id,
    ]);

    $firstReply = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $latestReply = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    TicketUserState::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'last_notified_activity_id' => $firstReply->id,
    ]);

    app(CopilotTicketService::class)->markTicketSeen($user, $ticket);

    $state = $ticket->ticketUserStates()->where('user_id', $user->id)->first();

    expect($state)
        ->last_seen_activity_id->toBe($latestReply->id)
        ->last_notified_activity_id->toBe($firstReply->id);
});

test('unread response ticket count ignores hidden turn changed activity after the seen response', function () {
    Event::fake();

    $user = User::factory()->create();
    $supporter = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    $reply = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::TurnChanged,
        'sender' => ActivitySender::System,
    ]);

    TicketUserState::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'last_seen_activity_id' => $reply->id,
    ]);

    expect(app(CopilotTicketService::class)->unreadResponseTicketCount($user))->toBe(0);
});

test('unread response ticket count excludes closed tickets', function () {
    Event::fake();

    $user = User::factory()->create();
    $supporter = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->closed()->create(['submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    expect(app(CopilotTicketService::class)->unreadResponseTicketCount($user))->toBe(0);
});

test('unread response ticket count includes open tickets with unseen responses', function () {
    Event::fake();

    $user = User::factory()->create();
    $supporter = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    expect(app(CopilotTicketService::class)->unreadResponseTicketCount($user))->toBe(1);
});

test('mark ticket seen never moves the pointer backwards', function () {
    Event::fake();

    $user = User::factory()->create();
    $supporter = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    $latestReply = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $hiddenActivity = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::TurnChanged,
        'sender' => ActivitySender::System,
    ]);

    TicketUserState::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'last_seen_activity_id' => $hiddenActivity->id,
    ]);

    app(CopilotTicketService::class)->markTicketSeen($user, $ticket);

    expect($ticket->ticketUserStates()->where('user_id', $user->id)->first())
        ->last_seen_activity_id->toBe($hiddenActivity->id);
});

test('an escalation the user opened stays out of their pane list, unread count and lookup', function () {
    Event::fake();

    $user = User::factory()->create();
    $padmission = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $escalation = escalationFrom(attributes: ['submitter_id' => $user->id]);
    Ticket::factory()->open()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $escalation->id,
        'user_id' => $padmission->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $service = app(CopilotTicketService::class);

    expect($service->visibleTickets($user, 'all'))->toBeEmpty()
        ->and($service->unreadResponseTicketCount($user))->toBe(0)
        ->and(fn () => $service->findVisibleTicket($user, $escalation->id))->toThrow(ModelNotFoundException::class);
});

test('a widget ticket filed into another panel stays in the pane list, unread count and lookup', function () {
    Event::fake();

    $user = User::factory()->create();
    $supporter = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $service = app(CopilotTicketService::class);

    expect($service->visibleTickets($user)->modelKeys())->toBe([$ticket->id])
        ->and($service->unreadResponseTicketCount($user))->toBe(1)
        ->and($service->findVisibleTicket($user, $ticket->id)->is($ticket))->toBeTrue();
});
