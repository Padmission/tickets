# Simple Notification Configuration Usage

## In your PanelProvider

```php
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
                ->onTicketAssigned(
                    supporterTriggered: ['notify_user' => false, 'notify_supporter' => true]
                )
                ->onTicketClosed(
                    userTriggered: ['notify_user' => true, 'notify_supporter' => false],
                    supporterTriggered: ['notify_user' => true, 'notify_supporter' => false]
                )
        )
);
```

## Default Behavior (if no configuration provided)

- **Ticket Created**
  - User-triggered: notify_user: true, notify_supporter: false
  - Supporter-triggered: notify_user: true, notify_supporter: true

- **Ticket Assigned**
  - User-triggered: notify_user: false, notify_supporter: false
  - Supporter-triggered: notify_user: false, notify_supporter: true

- **Ticket Activity**
  - User-triggered: notify_user: false, notify_supporter: true
  - Supporter-triggered: notify_user: true, notify_supporter: false

- **Ticket Closed**
  - User-triggered: notify_user: true, notify_supporter: false
  - Supporter-triggered: notify_user: true, notify_supporter: false
