<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Contracts\TicketActivityInterface;
use Padmission\Tickets\Models\Contracts\TicketDispositionInterface;
use Padmission\Tickets\Models\Contracts\TicketInterface;
use Padmission\Tickets\Models\Contracts\TicketNotificationInterface;
use Padmission\Tickets\Models\Contracts\TicketPriorityInterface;
use Padmission\Tickets\Models\Contracts\TicketStatusInterface;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\TicketPlugin;

uses(RefreshDatabase::class);

// Mock user-created models that extend the package base models
class CustomTicket extends \Padmission\Tickets\Models\Ticket
{
    protected $table = 'tickets';
    protected $fillable = ['subject', 'submitter_id', 'status_id', 'priority_id', 'escalation_level', 'turn'];
}

class CustomTicketActivity extends \Padmission\Tickets\Models\TicketActivity  
{
    protected $table = 'ticket_activities';
    protected $fillable = ['ticket_id', 'type', 'content', 'user_id', 'sender', 'data'];
    
    protected $casts = [
        'data' => 'array',
        'side' => \Padmission\Tickets\Enums\ActivitySide::class,
        'type' => \Padmission\Tickets\Enums\ActivityType::class,
        'sender' => \Padmission\Tickets\Enums\ActivitySender::class,
        'turn' => \Padmission\Tickets\Enums\Turn::class,
        'created_at' => 'immutable_datetime',
    ];
}

class CustomTicketDisposition extends \Padmission\Tickets\Models\TicketDisposition
{
    protected $table = 'ticket_dispositions';
    protected $fillable = ['display_name', 'color', 'panel'];
}

class CustomTicketStatus extends \Padmission\Tickets\Models\TicketStatus
{
    protected $table = 'ticket_statuses';
    protected $fillable = ['display_name', 'color', 'order', 'panel'];
}

class CustomTicketPriority extends \Padmission\Tickets\Models\TicketPriority
{
    protected $table = 'ticket_priorities';  
    protected $fillable = ['display_name', 'color', 'order', 'panel'];
}

class CustomTicketNotification extends \Padmission\Tickets\Models\TicketNotification
{
    protected $table = 'ticket_notifications';
    protected $fillable = ['ticket_id', 'user_id', 'read_at'];
}

beforeEach(function () {
    // Set up the package to use custom models
    config([
        'padmission-tickets.models' => [
            \Illuminate\Contracts\Auth\Authenticatable::class => \Padmission\Tickets\Tests\User::class,
            \Padmission\Tickets\Models\Ticket::class => CustomTicket::class,
            \Padmission\Tickets\Models\TicketActivity::class => CustomTicketActivity::class,
            \Padmission\Tickets\Models\TicketDisposition::class => CustomTicketDisposition::class,
            \Padmission\Tickets\Models\TicketStatus::class => CustomTicketStatus::class,
            \Padmission\Tickets\Models\TicketPriority::class => CustomTicketPriority::class,
            \Padmission\Tickets\Models\TicketNotification::class => CustomTicketNotification::class,
        ]
    ]);
    
    // Create a user for testing
    $this->user = \Padmission\Tickets\Tests\User::factory()->create();
    $this->actingAs($this->user);
    
    // Create required base data
    $this->status = CustomTicketStatus::create([
        'display_name' => 'Open',
        'color' => 'blue',
        'order' => 1,
        'panel' => 'test'
    ]);
    
    $this->priority = CustomTicketPriority::create([
        'display_name' => 'Normal',
        'color' => 'gray',
        'order' => 2,
        'panel' => 'test'
    ]);
});

it('ensures CustomTicket inherits TicketObserver and fires events', function () {
    Event::fake([TicketCreatedEvent::class]);
    
    // Create a ticket using the custom model
    $ticket = CustomTicket::create([
        'subject' => 'Test Ticket',
        'escalation_level' => 'default',
        'turn' => \Padmission\Tickets\Enums\Turn::User,
        'submitter_id' => $this->user->id,
        'status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
    ]);
    
    // Verify the observer fired the created event
    Event::assertDispatched(TicketCreatedEvent::class, function ($event) use ($ticket) {
        return $event->ticket->id === $ticket->id;
    });
    
    expect($ticket)->toBeInstanceOf(TicketInterface::class);
    expect($ticket)->toBeInstanceOf(CustomTicket::class);
});

it('ensures CustomTicket priority change triggers observer', function () {
    // Create another priority
    $highPriority = CustomTicketPriority::create([
        'display_name' => 'High',
        'color' => 'red',
        'order' => 1,
        'panel' => 'test'
    ]);
    
    // Create a ticket
    $ticket = CustomTicket::create([
        'subject' => 'Test Ticket',
        'escalation_level' => 'default',
        'turn' => \Padmission\Tickets\Enums\Turn::User,
        'submitter_id' => $this->user->id,
        'status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
    ]);
    
    $initialActivityCount = $ticket->ticketActivities()->count();
    
    // Change priority - this should trigger the observer
    $ticket->update(['priority_id' => $highPriority->id]);
    
    // Verify observer created a priority change activity
    $activities = $ticket->fresh()->ticketActivities;
    expect($activities)->toHaveCount($initialActivityCount + 1);
    
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
        'escalation_level' => 'default',
        'turn' => \Padmission\Tickets\Enums\Turn::User,
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
    
    expect($activity)->toBeInstanceOf(TicketActivityInterface::class);
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
    expect($disposition)->toBeInstanceOf(TicketDispositionInterface::class);
    expect($disposition)->toBeInstanceOf(CustomTicketDisposition::class);
});

it('ensures TicketStatus static methods work with custom models', function () {
    // Create closed status
    $closedStatus = CustomTicketStatus::create([
        'display_name' => 'Closed',
        'color' => 'gray',
        'order' => 10,
        'panel' => 'test'
    ]);
    
    // Test static methods work with custom model
    $openStatuses = CustomTicketStatus::getOpenStatuses();
    $closedStatusResult = CustomTicketStatus::getClosedStatus();
    
    expect($openStatuses)->toHaveCount(1);
    expect($openStatuses->first()->id)->toBe($this->status->id);
    expect($closedStatusResult->id)->toBe($closedStatus->id);
    
    expect($openStatuses->first())->toBeInstanceOf(TicketStatusInterface::class);
    expect($closedStatusResult)->toBeInstanceOf(TicketStatusInterface::class);
});

it('ensures all custom models implement their interfaces', function () {
    $ticket = new CustomTicket();
    $activity = new CustomTicketActivity();
    $disposition = new CustomTicketDisposition();
    $status = new CustomTicketStatus();
    $priority = new CustomTicketPriority();
    $notification = new CustomTicketNotification();
    
    expect($ticket)->toBeInstanceOf(TicketInterface::class);
    expect($activity)->toBeInstanceOf(TicketActivityInterface::class);
    expect($disposition)->toBeInstanceOf(TicketDispositionInterface::class);
    expect($status)->toBeInstanceOf(TicketStatusInterface::class);
    expect($priority)->toBeInstanceOf(TicketPriorityInterface::class);
    expect($notification)->toBeInstanceOf(TicketNotificationInterface::class);
});

it('ensures model resolution works with custom models', function () {
    expect(TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\Ticket::class))
        ->toBe(CustomTicket::class);
        
    expect(TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\TicketActivity::class))
        ->toBe(CustomTicketActivity::class);
        
    expect(TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\TicketDisposition::class))
        ->toBe(CustomTicketDisposition::class);
        
    expect(TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\TicketStatus::class))
        ->toBe(CustomTicketStatus::class);
        
    expect(TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\TicketPriority::class))
        ->toBe(CustomTicketPriority::class);
        
    expect(TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\TicketNotification::class))
        ->toBe(CustomTicketNotification::class);
});

it('ensures ticket observer handles status transition to closed', function () {
    // Create a closed status
    $closedStatus = CustomTicketStatus::create([
        'display_name' => 'Closed', 
        'color' => 'gray',
        'order' => 10,
        'panel' => 'test'
    ]);
    
    // Create a ticket
    $ticket = CustomTicket::create([
        'subject' => 'Test Ticket',
        'escalation_level' => 'default',
        'turn' => \Padmission\Tickets\Enums\Turn::User,
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

it('ensures custom models can add their own functionality', function () {
    // Test that custom models can extend functionality
    $customModel = new class extends \Padmission\Tickets\Models\Ticket {
        protected $table = 'tickets';
        
        public function customMethod(): string
        {
            return 'custom functionality';
        }
        
        public function getCustomAttribute(): string
        {
            return 'custom attribute';
        }
    };
    
    expect($customModel->customMethod())->toBe('custom functionality');
    expect($customModel->custom)->toBe('custom attribute');
    expect($customModel)->toBeInstanceOf(TicketInterface::class);
});
