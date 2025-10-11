# Tickets

[![Premium Package](https://img.shields.io/badge/package-premium-gold?style=flat-square)](https://tickets.padmission.com)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D11.0-red?style=flat-square)](composer.json)
[![Filament Version](https://img.shields.io/badge/filament-v3.x-purple?style=flat-square)](composer.json)

## Introduction

Tickets is a comprehensive support ticket management system for Filament applications. It provides a full-featured ticketing system with chat widget, email authentication, activity tracking, and extensive customization options.

## Development

The package is using `orchestral/testbench` for testing against a Laravel app.

`composer serve` will start the application as `http://tickets.test`. Make sure that test domain is available on your machine or change it to `http://localhost` in the `testbench.yaml` and `composer.json`. 

### Assets

When running `composer serve` Filament the latest assets are automatically published.

If you want to work on JS or CSS files, you can run:

```bash
npm install
npm run dev
```

This will start Vite, put the application in Dev Mode, remove existing Filament assets and serve the assets directly from the dist folder. It will also enable BrowserSync to reload the browser on changed.

Make sure you restart `npm run dev` if you restart `composer serve` or after you published the assets.

## Quick Start Examples

### Basic Support System

```php
// In your Panel Service Provider
use App\Models\User;
use Padmission\Tickets\AssignmentStrategies\AssignRandomUser;
use Padmission\Tickets\TicketPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->plugin(
            TicketPlugin::make()
                ->allSupportersQuery(fn () => User::role('support'))
                ->assignmentStrategy(new AssignRandomUser())
                ->registerResources()
                ->showChatWidget()
        );
}
```

### Multi-Panel Support System

```php
// Support Panel - Where tickets are managed
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('support')
        ->plugin(
            TicketPlugin::make()
                ->allSupportersQuery(fn () => User::role(['support', 'admin']))
                ->initialAssignmentSupportersQuery(fn () => User::role('tier-1'))
                ->assignmentStrategy(new AssignUserWithLeastTickets())
                ->registerResources()
        );
}

// Customer Panel - Where tickets are created
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('customer')
        ->plugin(
            TicketPlugin::make()
                ->targetPanel('support')
                ->showChatWidget()
        );
}
```

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
- 🎯 **Smart Assignment** - Automatic ticket assignment with flexible strategies
- 🏢 **Multi-Panel Support** - Route tickets from multiple panels to a central location

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

**Step 4**: Publish the assets

Add the following import to your custom theme:

```css
@import '../../../../vendor/padmission/tickets/resources/css/tickets.css';
```

**Step 5:** Configure the plugin in your Filament panel:

```php
use App\Models\User;
use Padmission\Tickets\TicketPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... other panel configuration
        ->plugin(
            TicketPlugin::make()
                ->allSupportersQuery(fn () => User::role(['support', 'admin']))
                ->registerResources()
        );
}
```

> **Important:** The `allSupportersQuery()` is required when registering resources. It defines all users who can support tickets in this panel.

**Step 6:** Set up your User model:

Add the `HasTickets` trait to your User model.

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Padmission\Tickets\Models\Concerns\User\HasTickets;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;

class User extends Authenticatable implements HasTicketDisplayName
{
    use HasTickets;
    
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

The `HasTickets` trait provides:
- `assignedTickets()` - Relationship to tickets assigned to this user
- `submittedTickets()` - Relationship to tickets submitted by this user

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

The package comes with a set of Filament resources to manage tickets. To enable ticket management in a panel:

```php
use App\Models\User;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role(['support', 'admin']))
    ->registerResources()
```

> **Important:** When registering resources, you must define `allSupportersQuery()`. This query determines which users can be assigned tickets through the UI.

This registers the following resources:
- **TicketResource** - Main ticket management with assignment controls
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

We define a basic policy, but you can swap it anytime with your implementation:

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

#### Email Authentication for Non-Authenticated Users

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

**How it works:**
1. User enters their email address
2. System sends a 6-digit OTP (One-Time Password) to their email
3. User enters the OTP to verify their identity
4. User can then submit tickets and view their ticket history

**Features:**
- Rate limiting on OTP requests (1 per minute)
- Rate limiting on OTP verification attempts (5 per minute)
- Configurable OTP expiration time
- Session-based authentication for verified users

#### File Uploads

If you want to allow users to upload files you can use the `->allowFileUploads()` method on the `ChatWidgetConfig`:


```php
use Filament\Support\Colors\Color;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->showChatWidget(config: ChatWidgetConfig::make()
        ->allowFileUploads()
    );
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

The package provides automatic ticket assignment with flexible configuration options.

#### Configuration

**All Supporters Query (Required for Resources)**

Define all users who can support tickets in a panel:

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role(['support', 'senior-support', 'admin']))
    ->registerResources()
```

**Initial Assignment Query (Optional)**

Define a subset of users for automatic assignment. If not specified, falls back to `allSupportersQuery()`:

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role(['support', 'senior-support', 'admin']))
    ->initialAssignmentSupportersQuery(fn () => User::role('support'))
    ->assignmentStrategy(new AssignUserWithLeastTickets())
```

#### Assignment Strategies

**DoNotAssign (Default)**

Leaves tickets unassigned:

```php
TicketPlugin::make()
    ->assignmentStrategy(new DoNotAssign())
```

**AssignUserWithLeastTickets**

Assigns to the user with fewest open tickets:

```php
use Padmission\Tickets\AssignmentStrategies\AssignUserWithLeastTickets;

TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->assignmentStrategy(new AssignUserWithLeastTickets())
```

**AssignRandomUser**

Randomly assigns to an eligible user:

```php
use Padmission\Tickets\AssignmentStrategies\AssignRandomUser;

TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->assignmentStrategy(new AssignRandomUser())
```

**AssignDefaultUser**

Assigns to a specific user:

```php
use Padmission\Tickets\AssignmentStrategies\AssignDefaultUser;

// Using user ID
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->assignmentStrategy(new AssignDefaultUser(userId: 1))

// Using callback
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->assignmentStrategy(new AssignDefaultUser(
        fn() => User::role('lead-support')->first()->id
    ))
```

#### Multi-Panel Configuration

Route tickets from multiple panels to a central support panel:

```php
// Main support panel - manages all tickets
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('support')
        ->plugin(
            TicketPlugin::make()
                ->allSupportersQuery(fn () => User::role(['support', 'admin']))
                ->initialAssignmentSupportersQuery(fn () => User::role('tier-1-support'))
                ->assignmentStrategy(new AssignUserWithLeastTickets())
                ->registerResources()
        );
}

// Customer panel - creates tickets but routes to support
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('customer')
        ->plugin(
            TicketPlugin::make()
                ->targetPanel('support') // Route tickets to support panel
                ->initialAssignmentSupportersQuery(fn () => User::role('customer-support'))
                ->showChatWidget()
                ->registerResources(false) // Don't show management UI here
        );
}

// Enterprise panel - creates tickets with different assignment
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('enterprise')
        ->plugin(
            TicketPlugin::make()
                ->targetPanel('support')
                ->initialAssignmentSupportersQuery(fn () => User::role('enterprise-support'))
                ->showChatWidget()
                ->registerResources(false)
        );
}
```

#### Source Panel Tracking

When tickets are created from different panels, the system tracks the source:
- The `panel` column stores where the ticket is managed
- The `source_panel` column stores where the ticket was created
- Source panel is automatically shown in the UI when multiple panels have the chat widget

#### Creating Custom Assignment Strategies

Extend `PanelAwareAssignmentStrategy` for automatic query handling:

```php
namespace App\AssignmentStrategies;

use Padmission\Tickets\AssignmentStrategies\PanelAwareAssignmentStrategy;
use Padmission\Tickets\Models\Ticket;

class AssignByWorkload extends PanelAwareAssignmentStrategy
{
    public function assign(Ticket $ticket): void
    {
        // Automatically uses initialAssignmentSupportersQuery or allSupportersQuery
        $user = $this->getEligibleUsersQuery($ticket)
            ->withCount([
                'assignedTickets as today_count' => fn ($q) => 
                    $q->whereDate('created_at', today())
            ])
            ->orderBy('today_count')
            ->first();
        
        if ($user) {
            $ticket->assignee_id = $user->id;
            // Do NOT call save() - handled automatically
        }
    }
}
```

#### Common Patterns

**Department-Based Assignment**

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::whereHas('department'))
    ->initialAssignmentSupportersQuery(fn () => User::query()
        ->whereHas('department', fn ($q) => $q->where('name', 'Support'))
    )
    ->assignmentStrategy(new AssignUserWithLeastTickets())
```

**Time-Based Assignment**

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->initialAssignmentSupportersQuery(fn () => User::role('support')
        ->whereHas('workSchedule', fn ($q) => 
            $q->where('day', now()->dayOfWeek)
              ->whereTime('start_time', '<=', now())
              ->whereTime('end_time', '>=', now())
        )
    )
    ->assignmentStrategy(new AssignRandomUser())
```

**Role-Based with Spatie Permissions**

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::permission('manage tickets'))
    ->initialAssignmentSupportersQuery(fn () => User::role('support'))
    ->assignmentStrategy(new AssignUserWithLeastTickets())
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

## Troubleshooting

### Exception: "requires an allSupportersQuery()"

This happens when registering resources without defining who can support tickets:

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->registerResources()
```

### No Users Being Assigned

Debug your queries to see what users are being returned:

```php
// Test all supporters query
$query = app()->call(TicketPlugin::get()->getAllSupportersQuery());
dd($query->count(), $query->pluck('name', 'id'));

// Test initial assignment query
$query = app()->call(TicketPlugin::get()->getInitialAssignmentSupportersQuery());
dd($query->count(), $query->pluck('name', 'id'));
```

### Tickets Going to Wrong Panel

Ensure your target panel ID matches exactly:

```php
// Panel ID must match exactly (case-sensitive)
->targetPanel('support') // Not 'Support' or 'SUPPORT'
```

### Assignment Strategy Not Working

1. Ensure the User model has the `HasTickets` trait
2. Check that your queries return users
3. Verify the assignment strategy is configured
4. Check Laravel logs for exceptions

## Support

For additional support:
- Contact support at [hello@padmission.com](mailto:hello@padmission.com)

## Credits

- [Padmission](https://github.com/Padmission)
- [All Contributors](../../contributors)

## License

The Tickets package is a private, paid package. All rights reserved. Unauthorized distribution, modification, or use is strictly prohibited.
