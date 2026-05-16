<?php

use App\Models\User;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketActivityService;

beforeEach(function () {
    $userModel = class_exists(Padmission\Tickets\Tests\User::class)
        ? Padmission\Tickets\Tests\User::class
        : User::class;

    $this->user = $userModel::factory()->create();
    $this->actingAs($this->user);

    $this->openStatus = TicketStatus::factory()->create([
        'display_name' => 'Open',
        'seed_key' => 'open',
        'order' => 1,
        'panel' => 'test',
    ]);

    $this->closedStatus = TicketStatus::factory()->create([
        'display_name' => 'Closed',
        'seed_key' => 'closed',
        'order' => 2,
        'panel' => 'test',
    ]);

    TicketPriority::factory()->create([
        'display_name' => 'Normal',
        'order' => 1,
        'panel' => 'test',
    ]);
});

it('returns support panel activities using the user visibility matrix', function () {
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->user->id,
        'status_id' => $this->openStatus->id,
    ]);

    foreach (ActivityType::cases() as $type) {
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => $type,
            'sender' => $type === ActivityType::Message ? ActivitySender::User : ActivitySender::System,
            'data' => ['from' => $this->openStatus->id, 'to' => $this->closedStatus->id],
        ]);
    }

    $types = app(TicketActivityService::class)
        ->getActivities($ticket, view: 'support')
        ->pluck('type')
        ->unique()
        ->values();

    expect($types)->toContain(ActivityType::Message)
        ->and($types)->toContain(ActivityType::Opened)
        ->and($types)->toContain(ActivityType::Escalated)
        ->and($types)->toContain(ActivityType::AssigneeChanged)
        ->and($types)->toContain(ActivityType::Joined)
        ->and($types)->toContain(ActivityType::Closed)
        ->and($types)->not->toContain(ActivityType::StatusChanged)
        ->and($types)->not->toContain(ActivityType::PriorityChanged)
        ->and($types)->not->toContain(ActivityType::TurnChanged);
});
