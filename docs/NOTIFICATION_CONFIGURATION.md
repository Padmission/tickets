# Notification Configuration System

The Ticket Plugin provides a flexible, panel-specific notification configuration system that allows granular control over who receives notifications based on event type and actor role.

## Overview

The notification system supports:
- **Panel-specific configuration** - Different rules per Filament panel
- **Actor-aware notifications** - Different rules based on who triggered the event
- **Event-based configuration** - Separate rules for created, assigned, activity, and closed events
- **Policy integration** - Uses existing Gate/Policy system for role determination
- **Multiple channels** - Email (implemented), Slack/SMS (ready for future)

## Quick Start

### Basic Configuration

```php
// In your PanelProvider (e.g., AppPanelProvider.php)
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\TicketPlugin;

$panel->plugin(
    TicketPlugin::make()
        ->registerResources()
        ->notificationConfiguration(
            NotificationConfiguration::make()
                ->onTicketCreated(
                    userTriggered: ['notify_user' => true, 'notify_supporter' => false],
                    supporterTriggered: ['notify_user' => true, 'notify_supporter' => true]
                )
                ->onTicketActivity(
                    userTriggered: ['notify_user' => false, 'notify_supporter' => true],
                    supporterTriggered: ['notify_user' => true, 'notify_supporter' => false]
                )
        )
);
```

### Fluent API Configuration

```php
NotificationConfiguration::make()
    ->onTicketCreated(function ($context) {
        $context->notifyBoth(); // Both user and supporter
    })
    ->onTicketActivity(function ($context) {
        $context->whenUserTriggered(['notify_user' => false, 'notify_supporter' => true])
                ->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
    })
    ->onTicketClosed(function ($context) {
        $context->notifyUser(); // Only the ticket submitter
    })
```

## Core Concepts

### Actor Types

The system determines who triggered an event and applies different rules:

- **User-triggered**: Event triggered by the ticket submitter
- **Supporter-triggered**: Event triggered by someone with `update` permission on the ticket

### Event Types

Four main event types are supported:

1. **Ticket Created** (`ticket_created`) - When a new ticket is created
2. **Ticket Assigned** (`ticket_assigned`) - When a ticket is assigned to someone
3. **Ticket Activity** (`ticket_activity`) - When messages or updates are added
4. **Ticket Closed** (`ticket_closed`) - When a ticket is closed

### Recipients

- **User** (`notify_user`): The ticket submitter
- **Supporter** (`notify_supporter`): The ticket assignee

## Configuration Methods

### Array Format

```php
->onTicketCreated(
    userTriggered: [
        'notify_user' => true,
        'notify_supporter' => false,
        'email_user' => true,
        'slack_supporter' => false,
    ],
    supporterTriggered: [
        'notify_user' => true,
        'notify_supporter' => true,
        'email_user' => true,
        'email_supporter' => true,
    ]
)
```

### Boolean Helper Methods

```php
->onTicketCreated(function ($context) {
    $context->notifyUser();           // Only notify ticket submitter
    $context->notifySupporter();      // Only notify ticket assignee  
    $context->notifyBoth();           // Notify both
    $context->notifyNone();           // Notify neither
})
```

### Conditional Configuration

```php
->onTicketActivity(function ($context) {
    $context->when(auth()->user()->isAdmin(), function ($ctx) {
        $ctx->notifyBoth();
    })->unless(app()->environment('testing'), function ($ctx) {
        $ctx->enableChannel('slack', 'supporter');
    });
})
```

### Environment-Specific Configuration

```php
->onTicketCreated(function ($context) {
    $context->inEnvironment('production', function ($ctx) {
        $ctx->enableChannel('slack', 'both');
    })->inEnvironment('local', function ($ctx) {
        $ctx->notifyNone(); // Don't spam in development
    });
})
```

### Channel-Based Configuration

```php
->onTicketActivity(function ($context) {
    $context->enableChannel('email', 'both')        // Email to both
            ->enableChannel('slack', 'supporter')   // Slack to supporter only
            ->disableChannel('sms', 'user');        // No SMS to user
})
```

## Default Behavior

When no configuration is provided, the system uses sensible defaults:

### Ticket Created
- **User-triggered**: Notify user only
- **Supporter-triggered**: Notify both user and supporter

### Ticket Assigned  
- **User-triggered**: Notify neither (users can't assign)
- **Supporter-triggered**: Notify supporter only

### Ticket Activity
- **User-triggered**: Notify supporter only (user gets copy via other means)
- **Supporter-triggered**: Notify user only (supporter knows they replied)

### Ticket Closed
- **User-triggered**: Notify user only (confirmation)
- **Supporter-triggered**: Notify user only (closed by support)

## Panel-Specific Configuration

Each Filament panel can have completely different notification rules:

```php
// AppPanelProvider.php - Customer portal
NotificationConfiguration::make()
    ->onTicketCreated(function ($context) {
        $context->notifySupporter(); // Only notify support team
    })

// AdminPanelProvider.php - Admin interface  
NotificationConfiguration::make()
    ->onTicketCreated(function ($context) {
        $context->notifyBoth()       // Notify everyone
                ->enableChannel('slack', 'supporter'); // Plus Slack
    })
```

## Advanced Examples

### Business Hours Configuration

```php
->onTicketCreated(function ($context) {
    $isBusinessHours = now()->between('09:00', '17:00') && now()->isWeekday();
    
    $context->when($isBusinessHours, function ($ctx) {
        $ctx->notifyBoth()->enableChannel('slack', 'supporter');
    })->unless($isBusinessHours, function ($ctx) {
        $ctx->notifySupporter(); // Only email after hours
    });
})
```

### Priority-Based Configuration

```php
->onTicketCreated(function ($context, $ticket) {
    if ($ticket->priority?->display_name === 'Urgent') {
        $context->notifyBoth()
                ->enableChannel('sms', 'supporter')
                ->enableChannel('slack', 'both');
    } else {
        $context->notifySupporter();
    }
})
```

### User Role-Based Configuration

```php
->onTicketActivity(function ($context, $ticket, $actor) {
    if ($actor?->hasRole('admin')) {
        $context->notifyUser(); // Admin replies notify user
    } else {
        $context->notifySupporter(); // User messages notify support
    }
})
```

## Testing

The notification configuration system is fully testable:

```php
test('custom notification configuration works', function () {
    $config = NotificationConfiguration::make()
        ->onTicketCreated(function ($context) {
            $context->notifySupporter();
        });
    
    $panel = Filament::getCurrentPanel();
    $plugin = TicketPlugin::make()->notificationConfiguration($config);
    $panel->plugin($plugin);
    
    // Create ticket and verify only supporter gets notified
    $ticket = Ticket::factory()->create();
    
    // Assert notification behavior...
});
```

## Migration from Legacy System

If you were previously using the system without explicit configuration, it will continue to work with the new defaults. To customize:

1. Add `notificationConfiguration()` to your `TicketPlugin::make()` call
2. Configure each event type as needed
3. Test the behavior matches your expectations

## Future Channels

The system is designed to support additional notification channels:

```php
// Future functionality (not yet implemented)
->enableChannel('sms', 'supporter')      // SMS notifications
->enableChannel('slack', 'both')         // Slack notifications  
->enableChannel('webhook', 'supporter')  // Webhook notifications
```

## Troubleshooting

### Common Issues

1. **No notifications being sent**: Check that your configuration has `notify_user` or `notify_supporter` set to `true`

2. **Wrong people getting notified**: Verify your actor type determination by checking who triggered the event

3. **Panel-specific config not working**: Ensure each panel registers its own plugin instance with different configurations

### Debug Configuration

```php
// Add this to see what configuration is being used
->onTicketCreated(function ($context, $ticket, $actor) {
    \Log::info('Notification config', [
        'actor_id' => $actor?->id,
        'actor_type' => /* determined based on permissions */,
        'ticket_id' => $ticket->id,
    ]);
    
    $context->notifyBoth();
})
```

## Performance Considerations

- Configuration evaluation is cached per event
- Policy checks are only performed once per notification cycle
- No database queries are added to the notification flow
- All configuration is resolved at plugin registration time

## API Reference

### NotificationConfiguration Methods

- `make()` - Create new configuration instance
- `onTicketCreated($userTriggered, $supporterTriggered)` - Configure ticket creation
- `onTicketAssigned($userTriggered, $supporterTriggered)` - Configure ticket assignment
- `onTicketActivity($userTriggered, $supporterTriggered)` - Configure ticket activity
- `onTicketClosed($userTriggered, $supporterTriggered)` - Configure ticket closure

### Context Methods (Fluent API)

- `notifyUser()` - Notify ticket submitter only
- `notifySupporter()` - Notify ticket assignee only
- `notifyBoth()` - Notify both user and supporter
- `notifyNone()` - Disable all notifications
- `when($condition, $callback)` - Conditional configuration
- `unless($condition, $callback)` - Inverse conditional configuration
- `inEnvironment($env, $callback)` - Environment-specific configuration
- `enableChannel($channel, $recipient)` - Enable specific channel
- `disableChannel($channel, $recipient)` - Disable specific channel
