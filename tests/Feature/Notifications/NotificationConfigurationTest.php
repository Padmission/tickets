<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Listeners\TicketNotificationListener;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;

beforeEach(function () {
    Queue::fake();
    
    // Create test users
    $this->submitter = User::factory()->create(['name' => 'Submitter']);
    $this->supporter = User::factory()->create(['name' => 'Supporter']);
    
    // Create ticket statuses and priorities
    \Padmission\Tickets\Models\TicketStatus::factory()->create([
        'display_name' => 'Open',
        'order' => 1,
        'panel' => 'test',
    ]);
    
    \Padmission\Tickets\Models\TicketPriority::factory()->create([
        'display_name' => 'Normal',
        'order' => 1,
        'panel' => 'test',
    ]);
    
    // Setup Gate to identify supporters
    Gate::define('update', function ($user, $ticket) {
        // In our test, the supporter can update any ticket
        return $user->id === $this->supporter->id;
    });
});

// Helper function to create a listener with immediate notifications
function createListener() {
    $recipientService = \Mockery::mock(NotificationRecipientService::class)->makePartial();
    $recipientService->shouldReceive('getUserNotificationStrategy')
        ->andReturn(NotificationStrategy::Immediate);
    
    return new TicketNotificationListener($recipientService);
}

test('ticket creation with default configuration sends notifications correctly', function () {    // Setup plugin with default configuration
    $plugin = TicketPlugin::make()
        ->notificationConfiguration(NotificationConfiguration::make());
    app()->instance('filament.plugins.padmission-tickets', $plugin);
    
    $listener = createListener();
    
    // Test 1: User creates ticket - should notify user only
    $this->actingAs($this->submitter);
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    $event = new TicketCreatedEvent($ticket, $this->submitter);
    $listener->handle($event);
    
    // Should dispatch notification to submitter only (user-triggered: notify_user: true, notify_supporter: false)
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id && 
               $job->notificationType === 'created';
    });
    
    Queue::assertPushed(NotificationJob::class, 1); // Only one notification
    
    // Clear queue for next test
    Queue::fake();
    
    // Test 2: Supporter creates ticket - should notify both    $this->actingAs($this->supporter);
    $ticket2 = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    $event2 = new TicketCreatedEvent($ticket2, $this->supporter);
    $listener->handle($event2);
    
    // Should dispatch notifications to both (supporter-triggered: notify_user: true, notify_supporter: true)
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id;
    });
    
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id;
    });
    
    Queue::assertPushed(NotificationJob::class, 2); // Two notifications
});

test('ticket activity with default configuration sends notifications correctly', function () {
    // Setup plugin with default configuration
    $plugin = TicketPlugin::make()
        ->notificationConfiguration(NotificationConfiguration::make());
    app()->instance('filament.plugins.padmission-tickets', $plugin);
    
    $listener = createListener();
    
    $ticket = Ticket::factory()->create([        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    // Test 1: User adds activity - should notify supporter only
    $this->actingAs($this->submitter);
    $event = new TicketActivityEvent($ticket, ActivityType::Message, null, $this->submitter);
    $listener->handle($event);
    
    // Should dispatch notification to supporter only (user-triggered: notify_user: false, notify_supporter: true)
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id && 
               $job->notificationType === 'activity';
    });
    
    Queue::assertPushed(NotificationJob::class, 1);
    
    // Clear queue
    Queue::fake();
    
    // Test 2: Supporter adds activity - should notify user only
    $this->actingAs($this->supporter);
    $event2 = new TicketActivityEvent($ticket, ActivityType::Message, null, $this->supporter);
    $listener->handle($event2);
    
    // Should dispatch notification to user only (supporter-triggered: notify_user: true, notify_supporter: false)
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id;
    });
    
    Queue::assertPushed(NotificationJob::class, 1);
});

test('ticket assignment with default configuration sends notifications correctly', function () {
    // Setup plugin with default configuration
    $plugin = TicketPlugin::make()
        ->notificationConfiguration(NotificationConfiguration::make());
    app()->instance('filament.plugins.padmission-tickets', $plugin);
    
    $listener = createListener();
    
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    // Test: Supporter assigns ticket - should notify supporter only
    $this->actingAs($this->supporter);
    $event = new TicketAssignedEvent($ticket, $this->supporter);
    $listener->handle($event);
    
    // Should dispatch notification to supporter only (supporter-triggered: notify_user: false, notify_supporter: true)
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id && 
               $job->notificationType === 'assigned';
    });
    
    Queue::assertPushed(NotificationJob::class, 1);
});

test('custom notification configuration overrides defaults correctly', function () {
    // Get the existing plugin from the panel and update its configuration
    $plugin = TicketPlugin::get();
    
    $customConfig = NotificationConfiguration::make()
        ->onTicketCreated(
            userTriggered: ['notify_user' => false, 'notify_supporter' => true],
            supporterTriggered: ['notify_user' => false, 'notify_supporter' => false]
        );
    
    // Use reflection to update the plugin's configuration
    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('notificationConfiguration');
    $property->setAccessible(true);
    $property->setValue($plugin, $customConfig);
    
    // Verify the configuration was set
    expect($plugin->getNotificationConfiguration()->getConfigurationFor('ticket_created', 'user_triggered'))
        ->toBe(['notify_user' => false, 'notify_supporter' => true]);
    
    $listener = createListener();
    
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    // Test: User creates ticket with custom config
    $this->actingAs($this->submitter);
    $event = new TicketCreatedEvent($ticket, $this->submitter);
    
    // Directly test the recipient service to debug
    $recipientService = new NotificationRecipientService();
    $recipients = $recipientService->getNotificationRecipients($event);
    expect($recipients)->toHaveCount(1);
    expect($recipients->first()->id)->toBe($this->supporter->id);
    
    $listener->handle($event);
    
    // Custom config: user-triggered should notify supporter only
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id && 
               $job->notificationType === 'created';
    });
});

test('no notifications are sent when configuration disables them', function () {
    // Get the existing plugin from the panel and update its configuration
    $plugin = TicketPlugin::get();
    
    $customConfig = NotificationConfiguration::make()
        ->onTicketCreated(
            userTriggered: ['notify_user' => false, 'notify_supporter' => false],
            supporterTriggered: ['notify_user' => false, 'notify_supporter' => false]
        );
    
    // Use reflection to update the plugin's configuration
    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('notificationConfiguration');
    $property->setAccessible(true);
    $property->setValue($plugin, $customConfig);
    
    $listener = createListener();
    
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    // Create ticket - should send no notifications
    $event = new TicketCreatedEvent($ticket, $this->submitter);
    $listener->handle($event);
    
    Queue::assertNothingPushed();
});

test('notifications handle missing assignee gracefully', function () {
    // Setup plugin with default configuration
    $plugin = TicketPlugin::make()
        ->notificationConfiguration(NotificationConfiguration::make());
    app()->instance('filament.plugins.padmission-tickets', $plugin);
    
    $listener = createListener();
    
    // Create ticket without assignee
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => null,
    ]);
    
    // User creates ticket - should only notify user (assignee doesn't exist)
    $event = new TicketCreatedEvent($ticket, $this->submitter);
    $listener->handle($event);
    
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id;
    });
    
    Queue::assertPushed(NotificationJob::class, 1);
});

test('actor determination works correctly without explicit actor', function () {
    // Setup plugin with default configuration
    $plugin = TicketPlugin::make()
        ->notificationConfiguration(NotificationConfiguration::make());
    app()->instance('filament.plugins.padmission-tickets', $plugin);
    
    $listener = createListener();
    
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    // Activity event without actor - should be treated as user-triggered
    $event = new TicketActivityEvent($ticket, ActivityType::Message, null, null);
    $listener->handle($event);
    
    // Default for user-triggered activity: notify supporter only
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id;
    });
    
    Queue::assertPushed(NotificationJob::class, 1);
});

test('custom notification configuration for activities works correctly', function () {
    // Get the existing plugin from the panel and update its configuration
    $plugin = TicketPlugin::get();
    
    $customConfig = NotificationConfiguration::make()
        ->onTicketActivity(
            userTriggered: ['notify_user' => true, 'notify_supporter' => true],
            supporterTriggered: ['notify_user' => true, 'notify_supporter' => true]
        );
    
    // Use reflection to update the plugin's configuration
    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('notificationConfiguration');
    $property->setAccessible(true);
    $property->setValue($plugin, $customConfig);
    
    $listener = createListener();
    
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    // Test: User adds activity with custom config - should notify both
    $this->actingAs($this->submitter);
    $event = new TicketActivityEvent($ticket, ActivityType::Message, null, $this->submitter);
    $listener->handle($event);
    
    // Custom config: user-triggered activity should notify both
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id && 
               $job->notificationType === 'activity';
    });
    
    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id && 
               $job->notificationType === 'activity';
    });
});

