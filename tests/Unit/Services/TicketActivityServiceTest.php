<?php

use Illuminate\Support\Facades\Config;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    $this->service = new TicketActivityService;
    $this->ticket = Ticket::factory()->create();
    $this->user = User::factory()->create();
});

test('can get unread activities', function () {
    // Create an old activity
    TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(15),
    ]);

    // Create a new activity
    $newActivity = TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(2),
    ]);

    $activities = $this->service->getUnreadActivities($this->ticket, $this->user, 10);

    expect($activities)->toHaveCount(2);
    expect($activities->last()->id)->toBe($newActivity->id);
});

test('can get unread activities after last notified activity', function () {
    $oldActivity = TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(4),
    ]);

    TicketUserState::factory()->create([
        'user_id' => $this->user->id,
        'ticket_id' => $this->ticket->id,
        'last_notified_activity_id' => $oldActivity->id,
    ]);

    // Create a new activity (should be included - after the last notified activity)
    $newActivity = TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(2),
    ]);

    $activities = $this->service->getUnreadActivities($this->ticket, $this->user, 10);

    expect($activities)->toHaveCount(1);
    expect($activities->first()->id)->toBe($newActivity->id);
});

test('can get last seen for user and ticket', function () {
    $userB = User::factory()->create();
    $ticketB = Ticket::factory()->create();

    $lastSeen = TicketUserState::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
    ]);

    TicketUserState::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $userB->id,
    ]);

    TicketUserState::factory()->create([
        'ticket_id' => $ticketB->id,
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->getUserState($this->ticket, $this->user);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($lastSeen->id);
});

test('returns null when no last seen exists', function () {
    $lastSeen = $this->service->getUserState($this->ticket, $this->user);

    expect($lastSeen)->toBeNull();
});

test('respects max events configuration', function () {
    Config::set('padmission-tickets.notification-max-events', 2);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create 4 activities
    for ($i = 0; $i < 4; $i++) {
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
        ]);
    }

    // Use the activity service to get unread activities
    $activityService = app(TicketActivityService::class);
    $activities = $activityService->getUnreadActivities($ticket, $user, 2);

    // Should return 3 activities (maxEvents + 1) to check if there are more
    expect($activities)->toHaveCount(3);
});

test('returns null when user has no previous last seen for ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $lastSeen = app(TicketActivityService::class)->getUserState($ticket, $user);

    expect($lastSeen)->toBeNull();
});

test('gets last seen for specific user and ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create a last seen record
    $lastSeenRecord = $ticket->ticketUserStates()->create([
        'user_id' => $user->getKey(),
        'created_at' => now()->subHour(),
    ]);

    // Use the activity service to get the last seen
    $activityService = app(TicketActivityService::class);
    $lastSeen = app(TicketActivityService::class)->getUserState($ticket, $user);

    expect($lastSeen)
        ->not->toBeNull()
        ->id->toBe($lastSeenRecord->id)
        ->user_id->toBe($user->getKey());
});

test('activities are returned in ascending chronological order', function () {
    $this->actingAs($this->user);

    $activities = TicketActivity::factory()->count(3)->create([
        'ticket_id' => $this->ticket->id,
        'type' => ActivityType::Message,
    ]);

    $result = $this->service->getActivities($this->ticket);

    expect($result->pluck('id')->all())
        ->toBe($activities->pluck('id')->sort()->values()->all());
});

test('a limited window returns the latest activities in ascending order', function () {
    $this->actingAs($this->user);

    $activities = TicketActivity::factory()->count(3)->create([
        'ticket_id' => $this->ticket->id,
        'type' => ActivityType::Message,
    ]);

    $result = $this->service->getActivities($this->ticket, limit: 2);

    expect($result->pluck('id')->all())
        ->toBe($activities->pluck('id')->sort()->values()->slice(1)->values()->all());
});

test('mark as seen creates a state row for first contact', function () {
    $activity = TicketActivity::factory()->create(['ticket_id' => $this->ticket->id]);

    $this->service->markAsSeen($this->ticket, $this->user, $activity->id);

    expect($this->service->getUserState($this->ticket, $this->user))
        ->last_seen_activity_id->toBe($activity->id);
});

test('mark as seen never moves the pointer backwards', function () {
    [$older, $newer] = TicketActivity::factory()->count(2)->create([
        'ticket_id' => $this->ticket->id,
    ]);

    $this->service->markAsSeen($this->ticket, $this->user, $newer->id);
    $this->service->markAsSeen($this->ticket, $this->user, $older->id);

    expect($this->service->getUserState($this->ticket, $this->user))
        ->last_seen_activity_id->toBe($newer->id);
});

test('mark as sent never moves the pointer backwards', function () {
    [$older, $newer] = TicketActivity::factory()->count(2)->create([
        'ticket_id' => $this->ticket->id,
    ]);

    $this->service->markAsSent($this->ticket, $this->user, $newer->id);
    $this->service->markAsSent($this->ticket, $this->user, $older->id);

    expect($this->service->getUserState($this->ticket, $this->user))
        ->last_notified_activity_id->toBe($newer->id);
});

test('mark as seen does not clobber the notified pointer', function () {
    [$notified, $seen] = TicketActivity::factory()->count(2)->create([
        'ticket_id' => $this->ticket->id,
    ]);

    $this->service->markAsSent($this->ticket, $this->user, $notified->id);
    $this->service->markAsSeen($this->ticket, $this->user, $seen->id);

    expect($this->service->getUserState($this->ticket, $this->user))
        ->last_notified_activity_id->toBe($notified->id)
        ->last_seen_activity_id->toBe($seen->id);
});

test('activities perspective comes from the provided user when no one is authenticated', function () {
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    $supporterMessage = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => 'Support reply',
    ]);

    expect(auth()->user())->toBeNull();

    $forSubmitter = $this->service->getActivities($ticket, user: $submitter);
    $forSupporter = $this->service->getActivities($ticket, user: $supporter);

    expect($forSubmitter->firstWhere('id', $supporterMessage->id))
        ->side->toBe(ActivitySide::Other)
        ->userName->not->toBe(__('padmission-tickets::tickets.side_you'));

    expect($forSupporter->firstWhere('id', $supporterMessage->id))
        ->side->toBe(ActivitySide::Me)
        ->userName->toBe(__('padmission-tickets::tickets.side_you'));
});

test('supporter perspective includes management activity types without auth while submitter does not', function () {
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    $statusChange = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::StatusChanged,
        'data' => ['from' => null, 'to' => null],
    ]);

    expect(auth()->user())->toBeNull();

    expect($this->service->getActivities($ticket, user: $supporter)->pluck('id'))
        ->toContain($statusChange->id);

    expect($this->service->getActivities($ticket, user: $submitter)->pluck('id'))
        ->not->toContain($statusChange->id);
});

test('unread activities use the notifiable perspective', function () {
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => 'Support reply',
    ]);

    $activities = $this->service->getUnreadActivities($ticket, $submitter, 10);

    expect($activities->first())
        ->side->toBe(ActivitySide::Other);
});

test('mark as seen leaves the pointer alone while seen tracking is skipped', function () {
    $activity = TicketActivity::factory()->create(['ticket_id' => $this->ticket->id]);
    $received = [];

    $this->service->skipSeenTrackingWhen(function ($user, $ticket) use (&$received) {
        $received = [$user->getKey(), $ticket->getKey()];

        return true;
    });

    $this->service->markAsSeen($this->ticket, $this->user, $activity->id);

    expect($this->service->getUserState($this->ticket, $this->user))->toBeNull()
        ->and($received)->toBe([$this->user->getKey(), $this->ticket->getKey()]);

    $this->service->skipSeenTrackingWhen(fn () => false);
    $this->service->markAsSeen($this->ticket, $this->user, $activity->id);

    expect($this->service->getUserState($this->ticket, $this->user))
        ->last_seen_activity_id->toBe($activity->id);
});
