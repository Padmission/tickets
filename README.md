# Tickets

[![Premium Package](https://img.shields.io/badge/package-premium-gold?style=flat-square)](https://tickets.padmission.com)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D11.0-red?style=flat-square)](composer.json)
[![Filament Version](https://img.shields.io/badge/filament-v3.x-purple?style=flat-square)](composer.json)

## Introduction

Tickets is a comprehensive support ticket management system for Filament applications. It provides a full-featured ticketing system with chat widget, email authentication, activity tracking, and extensive customization options.

## Key Features

- 🎫 **Full Ticket Management** - Create, view, assign, and close tickets
- 💬 **Embedded Chat Widget** - Real-time support chat for your users
- 📧 **Email Authentication** - Allow non-authenticated users to submit tickets via email verification
- 👥 **Multi-Tenancy Support** - Built-in support for multi-tenant applications
- 📊 **Analytics Widgets** - Track open tickets, response times, and burndown charts
- 🔄 **Turn Management** - Track whose turn it is to respond (User or Supporter)
- 📝 **Activity Tracking** - Comprehensive logging of all ticket changes
- 🔔 **Flexible Notifications** - Multiple notification strategies
- 📎 **File Attachments** - Support for file uploads via Spatie Media Library

## Prerequisites

- **PHP**: 8.3 or higher
- **Laravel**: 11.0 or higher
- **Filament**: 3.0 or higher

## Getting Started

### Activating Your License

For distribution we use [Satis Padmission](https://satis.padmission.com/), a private Composer repository. During the purchasing process, Lemon Squeezy will provide you with a license key that you'll need for installation.

### Configure Composer Repository

Add the private repository to your `composer.json` file:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://satis.padmission.com"
        }
    ]
}
```

### Installation

**Step 1:** Install the package via Composer:

```
composer require padmission/tickets
```

When prompted, provide your authentication details:
- **Username**: Your email address (e.g., myname@example.com)
- **Password**: Your license key (e.g., 9f3a2e1d-5b7c-4f86-a9d0-3e1c2b4a5f8e)

**Step 2:** Run the migrations to set up the database tables:

```bash
php artisan migrate
```

**Step 3**: Publish the assets

```
php artisan filament:assets
```

**Step 4:** Add the plugin to your Filament panel:

```php
use Padmission\Tickets\TicketPlugin;

$panel->plugin(TicketPlugin::make());
```

**Step 5:** Implement the display name interface on your User model:

For better ticket activity messages, implement the `HasTicketDisplayName` interface on your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;

class User extends Authenticatable implements HasTicketDisplayName
{
    // ... your existing code ...

    /**
     * Get the display name for ticket activities and notifications
     */
    public function getNameForTickets(): string
    {
        return $this->name ?? $this->email ?? "User {$this->id}";
    }
}
```

> **Note:** If you don't implement this interface, the package will automatically fall back to using the `name` attribute, then `email`, and finally "user {id}" as the display name.

## Configuration

### Publishing Configuration

To customize the package settings, publish the configuration file:

```bash
php artisan vendor:publish --tag="padmission-tickets-config"
```

This will create a `config/padmission-tickets.php` file where you can configure:
- Model bindings
- Multi-tenancy settings
- Escalation levels
- Attachment storage settings

### Resources

The package comes with a set of Filament resources to manage tickets. If you want to manage tickets via this Filament panel, you can use the `->registerResources()` method:

```php
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->registerResources();
```

This registers the following resources:
- **TicketResource** - Main ticket management
- **StatusResource** - Manage ticket statuses
- **DispositionResource** - Manage ticket dispositions
- **PriorityResource** - Manage ticket priorities

For each resource you can easily overwrite its label, navigation group, sort, and navigation icon:

```php
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;

class YourServiceProvider {
    public function boot() {
        TicketResource::configure(
            modelLabel: 'Your Label',
            pluralModelLabel: fn () => __('your.model'),
            navigationGroup: 'New Group',
            navigationIcon: 'heroicon-o-tag',
            navigationSort: 10, 
        );
    }
}
```

## Widgets

This package comes with multiple Filament widgets that can be added to your dashboard:

- **OpenTicketsWidget** - Shows count of open tickets
- **OpenSupporterTickets** - Shows tickets assigned to supporters
- **TicketCloseTimeWidget** - Displays average ticket close times
- **TicketBurndownChartWidget** - Visualizes ticket closure trends

Widgets are registered automatically when using `->registerResources()`. You can disable this by using `->registerResources(shouldRegisterWidgets: false)`.

By default, it will show the `TicketStatsWidget` on the `ListTickets` page.

### Authentication

As it's hard to predict your authentication requirements, we don't define any for you. You *must* bring your own `TicketPolicy` and define scopes for Users and Ticket.

```php
use Filament\Facades\Filament;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;
use Illuminate\Support\Facades\Gate;

// Define your policy, which extends from `TicketPolicy`
Gate::policy(
    Ticket::class,
    YourTicketPolicy::class
);
```

The `TicketPolicy` will affect Tickets, but also Statuses, Priorities, and Dispositions. If you want specific rules for the latter ones, you can define a Policy for those.

### Email Authentication for Non-Authenticated Users

The package supports email-based authentication for non-authenticated users, allowing them to submit and track tickets without creating an account. This is particularly useful for password reset requests or public support systems.

To enable email authentication:

```php
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->showChatWidget(config: ChatWidgetConfig::make()
        ->allowEmailAuthentication(
            allow: true,
            allowGuests: true,
            otpExpiresAfterMinutes: 10
        )
    );
```

How it works:
1. User enters their email address
2. System sends a 6-digit OTP (One-Time Password) to their email
3. User enters the OTP to verify their identity
4. User can then submit tickets and view their ticket history

Features:
- Rate limiting on OTP requests (1 per minute)
- Rate limiting on OTP verification attempts (5 per minute)
- Configurable OTP expiration time
- Session-based authentication for verified users

### Dispositions

The package allows you to define custom dispositions for tickets. Dispositions are used to categorize tickets when they are closed. You can configure dispositions within each panel using the DispositionResource.

### Chat Widget

Users can create tickets via a chat widget. The widget provides a modern, real-time chat interface with:

- **Rich text editor** with formatting options (bold, links, lists)
- **Auto-response messages** after first user message
- **Turn management** - Shows whose turn it is to respond
- **File attachments** (when configured)
- **Keyboard shortcuts** for power users

To enable the widget in a panel, use the `->showChatWidget()` method:

```php
use Filament\Support\Colors\Color;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->showChatWidget(config: ChatWidgetConfig::make()
        ->introMessage('Welcome to our support system! How can we help you today?')
        ->autoResponse('Thanks for your message! A support agent will be with you shortly.')
        ->placeholder('Type your message here...')
        ->primaryColor(Color::Cyan)
    );
```

If you want to render the chat widget outside a Filament panel add the Blade component at the end of your body tag:

```blade
<x-padmission-tickets::chat-widget />
```

Make sure the CSRF token is included in your HTML head section:

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### Multi-Tenancy Support

The package includes built-in support for multi-tenant applications. Enable it in your configuration:

```php
// config/padmission-tickets.php
return [
    'tenancy' => [
        'enabled' => true,
        'tenancy_model' => App\Models\Tenant::class,
    ],
];
```

The package automatically handles:
- Foreign key constraints based on your tenant model
- UUID/ULID support for tenant IDs
- Tenant isolation for all ticket operations

### Escalation Levels

COMING SOON

### Turn Management

The package automatically tracks whose "turn" it is to respond to a ticket:
- **User Turn** - Waiting for user response
- **Supporter Turn** - Waiting for support agent response

This helps support teams prioritize tickets that need attention. Turn changes are automatically logged in the activity history.

### Ticket Assignment

The package comes with two default ticket assignment strategies. You can customize the assignment logic by implementing your own `AssignmentStrategy` class. The default strategies are:

- `AssignDefaultUser`: Assigns tickets to a fixed user
- `AssignUserWithLeastTickets`: Assigns tickets to the user with the least number of open tickets

```php
use Padmission\Tickets\AssignmentStrategies\AssignDefaultUser;
use Padmission\Tickets\AssignmentStrategies\AssignUserWithLeastTickets;
use Padmission\Tickets\TicketPlugin;

// Assign to specific user
TicketPlugin::make()
    ->assignmentStrategy(
        new AssignDefaultUser(userId: 1)
    );

// Or assign to user with least tickets
TicketPlugin::make()
    ->assignmentStrategy(
        new AssignUserWithLeastTickets()
    );
```

### Notification Strategies

The package comes with three default notification strategies. You can customize this by implementing your own `NotificationStrategy` class. The default strategies are:

- `NotifyEmail`: Notifies one or multiple emails
- `NotifyAllUsers`: Notifies all users that can access the ticket
- `NotifyAssignedUser`: Notifies the user assigned to the ticket

```php
use Padmission\Tickets\NotificationStrategies\NotifyEmail;
use Padmission\Tickets\NotificationStrategies\NotifyAllUsers;
use Padmission\Tickets\NotificationStrategies\NotifyAssignedUser;
use Padmission\Tickets\TicketPlugin;

// Notify specific emails
TicketPlugin::make()
    ->notificationStrategy(
        new NotifyEmail(['support@example.com', 'admin@example.com'])
    );

// Or notify all users who can view tickets
TicketPlugin::make()
    ->notificationStrategy(
        new NotifyAllUsers()
    );

// Or notify only the assigned user
TicketPlugin::make()
    ->notificationStrategy(
        new NotifyAssignedUser()
    );
```

### Notification Configuration Per Panel

The package supports granular control over who receives notifications based on event type and actor role. You can configure different notification rules for each Filament panel using a fluent API.

```php
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationTrigger;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\TicketPlugin;

$panel->plugin(
    TicketPlugin::make()
        ->notificationConfiguration(
            NotificationConfiguration::make()
                ->on(
                    TicketCreatedEvent::class,
                    fn (NotificationTrigger $trigger) => match ($trigger) {
                        NotificationTrigger::User => NotificationRecipient::User,
                        NotificationTrigger::Supporter => NotificationRecipient::Both,
                    }
                )
                ->on(
                    TicketActivityEvent::class,
                    fn (NotificationTrigger $trigger) => match ($trigger) {
                        NotificationTrigger::User => NotificationRecipient::Supporter,
                        NotificationTrigger::Supporter => NotificationRecipient::User,
                    }
                )
                ->on(
                    TicketAssignedEvent::class,
                    fn (NotificationTrigger $trigger) => match ($trigger) {
                        NotificationTrigger::Supporter => NotificationRecipient::Supporter,
                        default => NotificationRecipient::None,
                    }
                )
                ->on(
                    TicketClosedEvent::class,
                    fn (NotificationTrigger $trigger) => match ($trigger) {
                        NotificationTrigger::User => NotificationRecipient::Supporter,
                        NotificationTrigger::Supporter => NotificationRecipient::User,
                    }
                )
        )
);
```

#### How It Works

The notification system uses two key enums:

**NotificationTrigger** - Who triggered the event:
- `NotificationTrigger::User` - The ticket submitter performed the action
- `NotificationTrigger::Supporter` - Someone with ticket management permissions performed the action

**NotificationRecipient** - Who should be notified:
- `NotificationRecipient::User` - Notify the ticket submitter only
- `NotificationRecipient::Supporter` - Notify the assigned supporter only
- `NotificationRecipient::Both` - Notify both user and supporter
- `NotificationRecipient::None` - Don't send any notifications

For each event type, you define a closure that receives the trigger type and returns who should be notified. This allows for flexible notification rules based on who initiated the action.

#### Default Behavior

The package provides sensible defaults if no configuration is provided:

**Ticket Created**
- User-triggered: Notifies user only
- Supporter-triggered: Notifies both user and supporter

**Ticket Assigned**
- Supporter-triggered: Notifies supporter only
- User-triggered: No notifications (users cannot assign tickets)

**Ticket Activity** (messages, comments)
- User-triggered: Notifies supporter only
- Supporter-triggered: Notifies user only

**Ticket Closed**
- User-triggered: Notifies supporter only
- Supporter-triggered: Notifies user only

#### Per-Panel Configuration

Since configuration is set at the panel level, you can have different rules for different panels:

```php
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationTrigger;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Events\TicketActivityEvent;

// Admin Panel - Notify all parties for everything
$adminPanel->plugin(
    TicketPlugin::make()
        ->notificationConfiguration(
            NotificationConfiguration::make()
                ->on(
                    TicketCreatedEvent::class,
                    fn (NotificationTrigger $trigger) => NotificationRecipient::Both
                )
                ->on(
                    TicketActivityEvent::class,
                    fn (NotificationTrigger $trigger) => NotificationRecipient::Both
                )
        )
);

// Customer Panel - More restrictive notifications
$customerPanel->plugin(
    TicketPlugin::make()
        ->notificationConfiguration(
            NotificationConfiguration::make()
                ->on(
                    TicketCreatedEvent::class,
                    fn (NotificationTrigger $trigger) => match ($trigger) {
                        NotificationTrigger::User => NotificationRecipient::User,
                        NotificationTrigger::Supporter => NotificationRecipient::None,
                    }
                )
                ->on(
                    TicketActivityEvent::class,
                    fn (NotificationTrigger $trigger) => match ($trigger) {
                        NotificationTrigger::User => NotificationRecipient::None,
                        NotificationTrigger::Supporter => NotificationRecipient::User,
                    }
                )
        )
);
```

#### Custom Notification Logic

You can also implement complex notification logic based on your business requirements:

```php
->on(
    TicketCreatedEvent::class,
    function (NotificationTrigger $trigger) {
        // Custom logic based on time of day, user type, etc.
        if ($trigger === NotificationTrigger::User) {
            // During business hours, notify both
            if (now()->hour >= 9 && now()->hour < 17) {
                return NotificationRecipient::Both;
            }
            // Outside business hours, just notify user
            return NotificationRecipient::User;
        }
        
        return NotificationRecipient::Both;
    }
)

### Activity Tracking

All ticket changes are automatically tracked in the activity log:

- **Message** - Regular ticket messages
- **Internal Message** - Internal notes not visible to end users
- **Opened** - Ticket creation
- **Priority Changed** - Priority modifications
- **Status Changed** - Status updates
- **Turn Changed** - Turn ownership changes
- **Closed** - Ticket closure with disposition

Activities include:
- User who made the change
- Timestamp
- Previous and new values (where applicable)
- Soft delete support for audit trails

### File Attachments

The package supports file attachments via Spatie Media Library. Configure the storage disk in your configuration:

```php
// config/padmission-tickets.php
'attachments' => [
    'storage' => env('MEDIA_DISK', 's3'),
],
```

Files can be attached to ticket messages through the chat widget or API.

## Customization

### Custom Models

You can extend the package models with your own:

```php
// config/padmission-tickets.php
'models' => [
    Padmission\Tickets\Models\Ticket::class => App\Models\Ticket::class,
    Padmission\Tickets\Models\TicketActivity::class => App\Models\TicketActivity::class,
    // ... other models
],
```

Your custom models should extend the package models to ensure compatibility.

### Custom Assignment Strategy

Create your own assignment strategy:

```php
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\Models\Ticket;

class RoundRobinAssignment implements AssignmentStrategy
{
    public function assign(Ticket $ticket): void
    {
        // Your custom logic here
    }
}
```

### Custom Notification Strategy

Create your own notification strategy:

```php
use Padmission\Tickets\NotificationStrategies\NotificationStrategy;
use Padmission\Tickets\Models\Ticket;

class SlackNotification implements NotificationStrategy
{
    public function notify(Ticket $ticket): void
    {
        // Send to Slack
    }
}
```


## Custom Models & Jobs

### Using Custom Models

To use custom models with this package, you need to:

1. **Create your custom model class** by extending the base model
2. **Update the configuration** to map the base class to your custom class

### Ticket Model Example

```php
<?php

namespace App\Models;

use Padmission\Tickets\Models\Ticket as BaseTicket;

class CustomTicket extends BaseTicket
{
    // Your custom functionality
    // The observers are automatically inherited from the base class
    
    public function someCustomMethod()
    {
        // Your custom logic here
    }
}
```

Then update your `config/padmission-tickets.php` file:

```php
'models' => [
    // ... other models
    \Padmission\Tickets\Models\Ticket::class => \App\Models\CustomTicket::class,
],
```

### Other Model Examples

For other models, follow the same pattern:

```php
// Custom TicketActivity
namespace App\Models;

use Padmission\Tickets\Models\TicketActivity as BaseTicketActivity;

class CustomTicketActivity extends BaseTicketActivity
{
    // Your custom functionality
}

// Custom TicketStatus  
namespace App\Models;

use Padmission\Tickets\Models\TicketStatus as BaseTicketStatus;

class CustomTicketStatus extends BaseTicketStatus
{
    // Your custom functionality
}
```

Then update the config:

```php
'models' => [
    // ... other models
    \Padmission\Tickets\Models\TicketActivity::class => \App\Models\CustomTicketActivity::class,
    \Padmission\Tickets\Models\TicketStatus::class => \App\Models\CustomTicketStatus::class,
],
```

### Custom Jobs

To use custom job classes, you need to:

1. **Create your custom job class** by extending the base job
2. **Update the configuration** to map the base class to your custom class

#### Extending NotificationJob

To add custom properties like `tenantId` to the notification job:

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;

class CustomNotificationJob extends NotificationJob
{
    public ?int $tenantId = null;

    /**
     * Override to add custom initialization
     */
    protected function initializeJob(Authenticatable $user, Ticket $model): void
    {
        // Add your custom logic here
        $this->tenantId = $user->tenant_id ?? null;
        
        // Set custom queue, delay, etc.
        $this->onQueue('tenant-' . $this->tenantId);
    }

    /**
     * Override unique ID generation to include tenant
     */
    protected function buildUniqueId(): string
    {
        return parent::buildUniqueId() . '-tenant-' . $this->tenantId;
    }

    /**
     * Override notification sending for tenant-specific logic
     */
    protected function sendNotification(Authenticatable $user, Ticket $record, string $notificationClass): void
    {
        // Add tenant-specific notification logic
        if ($this->shouldSendNotification($user, $record)) {
            parent::sendNotification($user, $record, $notificationClass);
        }
    }

    /**
     * Custom logic to determine if notification should be sent
     */
    protected function shouldSendNotification(Authenticatable $user, Ticket $record): bool
    {
        // Add your business logic here
        return $user->tenant_id === $record->tenant_id;
    }

    /**
     * Override error handling
     */
    protected function handleException(\Exception $e): void
    {
        // Log errors with tenant context
        \Log::error('Notification job failed', [
            'tenant_id' => $this->tenantId,
            'ticket_id' => $this->getTicketKey(),
            'user_id' => $this->getUserId(),
            'error' => $e->getMessage(),
        ]);
    }
}
```

#### Register Custom Job

Configure the package to use your custom job in your `config/padmission-tickets.php`:

```php
'jobs' => [
    \Padmission\Tickets\Jobs\NotificationJob::class => \App\Jobs\CustomNotificationJob::class,
],
```

## Support

For additional support:
- Contact support at [hello@padmission.com](mailto:hello@padmission.com)

## Credits

- [Padmission](https://github.com/Padmission)
- [All Contributors](../../contributors)

## License

The Tickets package is a private, paid package. All rights reserved. Unauthorized distribution, modification, or use is strictly prohibited.

## Development

```bash
npm install
npm run dev
```

### Watch Mode

When running `npm run dev`, the package will be in debug mode and automatically load compiled assets directly from the dist file. No need for `php artisan filament:assets`.

Make sure you didn't publish the assets before running `npm run dev`, as this will cause the package to load the published assets instead of the compiled ones.

### BrowserSync

BrowserSync will reload the page when you make changes to the resources. You can configure the project url you are using for development and the BrowserSync port via the .env file
