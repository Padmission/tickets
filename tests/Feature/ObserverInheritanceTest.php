<?php

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketNotification;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicket;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicketActivity;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicketDisposition;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicketNotification;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicketPriority;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    // Set up the package to use custom models
    config([
        'padmission-tickets.models' => [
            Authenticatable::class => User::class,
            Ticket::class => CustomTicket::class,
            TicketActivity::class => CustomTicketActivity::class,
            TicketDisposition::class => CustomTicketDisposition::class,
            TicketStatus::class => CustomTicketStatus::class,
            TicketPriority::class => CustomTicketPriority::class,
            TicketNotification::class => CustomTicketNotification::class,
        ],
    ]);

    // Create a user for testing
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create required base data
    $this->status = CustomTicketStatus::create([
        'display_name' => 'Open',
        'color' => 'blue',
        'order' => 1,
        'panel' => 'test',
    ]);

    $this->priority = CustomTicketPriority::create([
        'display_name' => 'Normal',
        'color' => 'gray',
        'order' => 2,
        'panel' => 'test',
    ]);
});

it('ensures CustomTicket inherits TicketObserver and fires events', function () {
    Event::fake([TicketCreatedEvent::class]);

    // Create a ticket using the custom model
    $ticket = CustomTicket::create([
        'subject' => 'Test Ticket',
        'panel' => TicketPlugin::get()->getTargetPanelId() ?? Filament::getId(),
        'escalation_level' => 'default',
        'turn' => Turn::User,
        'submitter_id' => $this->user->id,
        'status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
    ]);

    // Verify the observer fired the created event
    Event::assertDispatched(TicketCreatedEvent::class, function ($event) use ($ticket) {
        return $event->ticket->id === $ticket->id;
    });

    expect($ticket)->toBeInstanceOf(CustomTicket::class);
});

it('ensures CustomTicket priority change triggers observer', function () {
    // Create another priority
    $highPriority = CustomTicketPriority::create([
        'display_name' => 'High',
        'color' => 'red',
        'order' => 1,
        'panel' => 'test',
    ]);

    // Create a ticket
    $ticket = CustomTicket::create([
        'subject' => 'Test Ticket',
        'panel' => TicketPlugin::get()->getTargetPanelId() ?? Filament::getId(),
        'escalation_level' => 'default',
        'turn' => Turn::User,
        'submitter_id' => $this->user->id,
        'status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
    ]);

    // Change priority - this should trigger the observer
    $ticket->update(['priority_id' => $highPriority->id]);

    // Verify observer created a priority change activity
    $activities = $ticket->fresh()->ticketActivities;
    expect($activities)->toHaveCount(1);

    $priorityActivity = $activities->where('type', ActivityType::PriorityChanged)->first();
    expect($priorityActivity)->not->toBeNull();
    expect($priorityActivity->data['from'])->toBe($this->priority->id);
    expect($priorityActivity->data['to'])->toBe($highPriority->id);
});

it('ensures CustomTicketActivity inherits TicketActivityObserver', function () {
    Event::fake([TicketActivityEvent::class]);

    // Create a ticket first
    $ticket = CustomTicket::create([
        'subject' => 'Test Ticket',
        'panel' => TicketPlugin::get()->getTargetPanelId() ?? Filament::getId(),
        'escalation_level' => 'default',
        'turn' => Turn::User,
        'submitter_id' => $this->user->id,
        'status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
    ]);

    // Create an activity using the custom model
    $activity = CustomTicketActivity::create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'content' => 'Test message',
        'sender' => ActivitySender::User,
        // Don't set user_id - let observer handle it
    ]);

    // Verify observer set the user_id
    expect($activity->fresh()->user_id)->toBe($this->user->id);

    // Verify observer fired the event
    Event::assertDispatched(TicketActivityEvent::class, function ($event) use ($ticket) {
        return $event->ticket->id === $ticket->id;
    });

    expect($activity)->toBeInstanceOf(CustomTicketActivity::class);
});

it('ensures CustomTicketDisposition inherits TicketDispositionObserver', function () {
    // Create a disposition using the custom model without panel
    $disposition = CustomTicketDisposition::create([
        'display_name' => 'Resolved',
        'color' => 'green',
        // Don't set panel - let observer handle it
    ]);

    // Verify observer set the panel
    expect($disposition->fresh()->panel)->toBe('test'); // Package TestCase sets panel to 'test'
    expect($disposition)->toBeInstanceOf(CustomTicketDisposition::class);
});

it('ensures TicketStatus static methods work with custom models', function () {
    // Create closed status
    $closedStatus = CustomTicketStatus::create([
        'display_name' => 'Closed',
        'color' => 'gray',
        'order' => 10,
        'panel' => 'test',
    ]);

    // Test static methods work with custom model
    $openStatuses = CustomTicketStatus::getOpenStatuses();
    $closedStatusResult = CustomTicketStatus::getClosedStatus();

    expect($openStatuses)->toHaveCount(1);
    expect($openStatuses->first()->id)->toBe($this->status->id);
    expect($closedStatusResult->id)->toBe($closedStatus->id);

    expect($openStatuses->first())->toBeInstanceOf(CustomTicketStatus::class);
    expect($closedStatusResult)->toBeInstanceOf(CustomTicketStatus::class);
});

it('ensures ticket observer handles status transition to closed', function () {
    // Create a closed status
    $closedStatus = CustomTicketStatus::create([
        'display_name' => 'Closed',
        'color' => 'gray',
        'order' => 10,
        'panel' => 'test',
    ]);

    // Create a ticket
    $ticket = CustomTicket::create([
        'subject' => 'Test Ticket',
        'panel' => TicketPlugin::get()->getTargetPanelId() ?? Filament::getId(),
        'escalation_level' => 'default',
        'turn' => Turn::User,
        'submitter_id' => $this->user->id,
        'status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
    ]);

    expect($ticket->closed_at)->toBeNull();

    // Change status to closed - this should trigger close logic
    $ticket->update(['status_id' => $closedStatus->id]);

    // Verify ticket was closed
    $ticket = $ticket->fresh();
    expect($ticket->closed_at)->not->toBeNull();

    // Verify close activity was created
    $closeActivity = $ticket->ticketActivities()
        ->where('type', ActivityType::Closed)
        ->first();
    expect($closeActivity)->not->toBeNull();
});
