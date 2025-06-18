<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    // Create test users
    $this->submitter = User::factory()->create();
    $this->supporter = User::factory()->create();
    
    // Create test ticket
    $this->ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);
    
    $this->recipientService = new NotificationRecipientService();
});

describe('Actor Type Determination', function () {
    
    test('determines user_triggered when actor is ticket submitter', function () {
        // Mock Gate to return false for update permission (not a supporter)
        Gate::shouldReceive('forUser')
            ->with($this->submitter)
            ->andReturnSelf();
        Gate::shouldReceive('allows')
            ->with('update', $this->ticket)
            ->andReturn(false);
            
        // Create event with submitter as actor
        $event = new TicketCreatedEvent($this->ticket, $this->submitter);
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->recipientService);
        $getActorTypeMethod = $reflection->getMethod('getActorType');
        $getActorTypeMethod->setAccessible(true);
        
        $actorType = $getActorTypeMethod->invoke($this->recipientService, $event);
        expect($actorType)->toBe('user_triggered');
    });
    
    test('determines supporter_triggered when actor has update permission', function () {
        // Mock Gate to return true for update permission (is a supporter)
        Gate::shouldReceive('forUser')
            ->with($this->supporter)
            ->andReturnSelf();
        Gate::shouldReceive('allows')
            ->with('update', $this->ticket)
            ->andReturn(true);
            
        // Create event with supporter as actor
        $event = new TicketCreatedEvent($this->ticket, $this->supporter);
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->recipientService);
        $getActorTypeMethod = $reflection->getMethod('getActorType');
        $getActorTypeMethod->setAccessible(true);
        
        $actorType = $getActorTypeMethod->invoke($this->recipientService, $event);
        expect($actorType)->toBe('supporter_triggered');
    });
    
    test('falls back to user_triggered when no actor provided', function () {
        // Create event without actor
        $event = new TicketCreatedEvent($this->ticket, null);
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->recipientService);
        $getActorTypeMethod = $reflection->getMethod('getActorType');
        $getActorTypeMethod->setAccessible(true);
        
        $actorType = $getActorTypeMethod->invoke($this->recipientService, $event);
        expect($actorType)->toBe('user_triggered');
    });
    
    test('supporter triggered event notifies correct recipients', function () {
        // Create a configuration for supporter triggered events
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                supporterTriggered: ['notify_user' => false, 'notify_supporter' => true]
            );
        
        // Set up plugin with config
        $panel = \Filament\Facades\Filament::getCurrentPanel();
        $plugin = \Padmission\Tickets\TicketPlugin::make()
            ->notificationConfiguration($config);
        $panel->plugin($plugin);
        
        // Mock Gate to return true for supporter update permission
        Gate::shouldReceive('forUser')
            ->with($this->supporter)
            ->andReturnSelf();
        Gate::shouldReceive('allows')
            ->with('update', $this->ticket)
            ->andReturn(true);
            
        // Test supporter-triggered event
        $supporterEvent = new TicketCreatedEvent($this->ticket, $this->supporter);
        $supporterRecipients = $this->recipientService->getNotificationRecipients($supporterEvent);
        
        // Should notify supporter only (assignee) since notify_supporter: true
        expect($supporterRecipients)->toHaveCount(1);
        expect($supporterRecipients->first()->id)->toBe($this->supporter->id);
    });
});
