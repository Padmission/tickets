# Padmission Tickets

[![Premium Package](https://img.shields.io/badge/package-premium-gold?style=flat-square)](https://tickets.padmission.com)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D11.0-red?style=flat-square)](composer.json)
[![Filament Version](https://img.shields.io/badge/filament-v3.x-purple?style=flat-square)](composer.json)

## Introduction


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

### Resources

The package comes with a set of Filament resources to manage tickets. If you want to manage tickets via this Filament panel, you can use the `->registerResources()` method:

```php
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->registerResources();
```

For each resource you can easily overwrite it's label, navigation group, and navigation icon:

```php
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;class YourServiceProvider {
    public function boot() {
        TicketResource::configure(
            modelLabel: 'Your Label',
            pluralModelLabel: fn () => __('your.model'),
            navigationGroup: 'New Group',
            navigationIcon: 'heroicon-o-tag' 
        );
    }
}
```

## Widgets
This package comes with multiple Filamnet widgets that can be added to your dashboard. You can find the widgets in the `Padmission\Tickets\Filament\Widgets` namespace. They are registered automatically when using `->registerResources()`. You can disable this by using `->registerResources(shouldRegisterWidgets: false)`.

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

### Dispositions

The package allows you to define custom dispositions for tickets. Dispositions are used to categorize tickets when they are closed.
You can configure dispositions within each panel using the DispositionResource.

### Chat Widget

Users can create tickets via a chat widget. To enable the widget in a panel, use the `->showChatWidget()` method. You can configure the chat widget via `ChatWidgetConfig`

```php
use Filament\Support\Colors\Color;use Padmission\Tickets\ChatWidgetConfig;use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->showChatWidget(config: ChatWidgetConfig::make()
        ->introMessage('Welcome to the support chat.')
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

### Escalation levels

You can have multiple panels with different "escalation levels". For example basic support and tech support. The standard level is `default`. You can configure your levels via `config/tickets.php`.

To set the level for a panel use the `->escalationLevel()` method:

```php
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->escalationLevel('tech');
```

### Ticket Assignment

The package comes with two default ticket assignment strategies. You can customize the assignment logic by implementing your own `AssignmentStrategy` class. The default strategies are:

- `AssignDefaultUser`: Assigns tickets to a fixed user
- `AssignUserWithLeastTickets`: Assigns tickets to the user with the least number of open tickets

```php
use Padmission\Tickets\AssignmentStrategies\AssignDefaultUser;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->assignmentStrategy(
        new AssignDefaultUser(1)
    );
```

### Notification about new tickets

The package comes with three default notification strategies. You can customize this by implementing your own `NotificationStrategy` class. The default strategies are:

- `NotifyEmail`: Notifies one or multiple emails
- `NotifyAllUsers`: Notifies all users that can access the ticket
- `NotifyAssignedUser`: Notifies the user assigned to the ticket

```php

use Padmission\Tickets\NotificationStrategies\NotifyEmail;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->notificationStrategy(
        new NotifyEmail(['info@example.com'])
    );
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
            'ticket_id' => $this->getModelId(),
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
