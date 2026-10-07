# Tickets

[![Premium Package](https://img.shields.io/badge/package-premium-gold?style=flat-square)](https://tickets.padmission.com)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/laravel-11--13-red?style=flat-square)](composer.json)
[![Filament Version](https://img.shields.io/badge/filament-v4%20%7C%20v5-purple?style=flat-square)](composer.json)

## Introduction

Tickets is a support ticket system for Filament applications. It gives each panel a ticket list and conversation view, a chat widget, email authentication for guests, activity tracking, and escalations: an organization's panel can hand a ticket to a central support panel and both sides keep talking on linked tickets.

## Development

The package is using `orchestral/testbench` for testing against a Laravel app.

`composer serve` will start the application as `http://127.0.0.1:8000`.


### Assets

When running `composer serve` the latest Filament assets are automatically published.

If you want to work on JS or CSS files, you can run:

```bash
npm install
npm run dev
```

This will start Vite, put the application in Dev Mode, remove existing Filament assets and serve the assets directly from the dist folder. It will also enable BrowserSync to reload the browser on changes.

Make sure you restart `npm run dev` if you restart `composer serve` or after you published the assets.

### Storage

To test storage features make sure to add a S3 compatible bucket to `testbench.yaml` env config. It defaults to a local minio install.

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
use Padmission\Tickets\AssignmentStrategies\AssignUserWithLeastTickets;

// Support Panel - Where tickets are managed
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('support')
        ->plugin(
            TicketPlugin::make()
                ->allSupportersQuery(fn () => User::role(['support', 'admin']))
                ->initialAssignmentSupportersQuery(fn () => User::role('tier-1'))
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
                // The strategy is read from the panel the ticket is created on,
                // the pool of people from the panel it goes to (see Ticket Assignment).
                ->assignmentStrategy(new AssignUserWithLeastTickets())
                ->showChatWidget()
        );
}
```

## Key Features

- 🎫 **Full Ticket Management** - Create, view, assign, close, reopen and delete tickets, one at a time or in bulk
- 💬 **Embedded Chat Widget** - Real-time support chat for your users
- 📧 **Email Authentication** - Allow non-authenticated users to submit tickets via email verification
- 👥 **Multi-Tenancy Support** - Built-in support for multi-tenant applications
- 📊 **Analytics Widgets** - Track open tickets, response times, and burndown charts
- 🔄 **Turn Management** - Track whose turn it is to respond (User or Supporter)
- 📝 **Activity Tracking** - Comprehensive logging of all ticket changes
- 🔔 **Flexible Notifications** - Per-panel recipient rules, immediate or debounced delivery
- 📎 **File Attachments** - Uploads go straight to an S3-compatible disk through presigned URLs
- 🎯 **Smart Assignment** - Automatic ticket assignment with flexible strategies
- 🏢 **Multi-Panel Support** - Route tickets from multiple panels to a central location
- ⬆️ **Escalations** - Link an organization's tickets to a ticket for a central support team, with hand over and take over

## Prerequisites

- **PHP**: 8.3 or higher
- **Laravel**: 11, 12 or 13
- **Filament**: 4 (4.0.4 or higher) or 5

## Getting Started

### Installation

> **Note:** The 4.x line has no tagged release yet. The Padmission apps install it as `4.x-dev` from the private GitHub repository, which needs a GitHub account with access to `Padmission/tickets`. Licence and distribution details for outside customers are not covered here.

**Step 1:** Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Padmission/tickets.git"
        }
    ]
}
```

**Step 2:** Require the 4.x branch:

```bash
composer require padmission/tickets:4.x-dev
```

A plain `composer require padmission/tickets` installs the latest tag, which is 3.x.

**Step 3:** Run the migrations to set up the database tables:

```bash
php artisan migrate
```

The package runs its own migrations. Set `run_migrations` to `false` in the config if you'd rather publish and manage them yourself.

**Step 4:** Publish the Filament assets:

```bash
php artisan filament:assets
```

`tickets.css` is registered as a Filament asset and loads on every panel page, so there's no need to also `@import` it into a custom theme. Importing it there as well just loads it twice.

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

**Step 7 (optional):** Seed the default statuses, priorities and dispositions:

```bash
php artisan tickets:seed
```

Statuses, priorities and dispositions are kept per panel (and per tenant when tenancy is on). The command seeds every panel that has the plugin. Options:

- `--tenant=ID` - seed one tenant instead of all of them
- `--only=dispositions,priorities,statuses,tickets,scenarios` - seed only some types. `scenarios` runs only when named (see [Seeding Demo Scenarios](#seeding-demo-scenarios))
- `--panel=ID` - with `scenarios`, seed that panel only
- `--force` - seed even when rows already exist

## Configuration

### Publishing Configuration

To customize the package settings, publish the configuration file:

```bash
php artisan vendor:publish --tag="padmission-tickets-config"
```

This will create a `config/padmission-tickets.php` file where you can configure:
- `run_migrations` - whether the package runs its own migrations (default `true`)
- `models` - model bindings, including the user model (`Authenticatable::class => User::class`)
- `jobs` - job bindings (see [Custom Jobs](#custom-jobs))
- `tenancy` - multi-tenancy settings
- `attachments` - the `disk` and `preview_disk` for uploaded files, and the `allowed_mime_types` an attachment may have
- `notifications` - the notification class sent for each event
- `notification-channels` - `mail` by default, add `database` for Filament's notification panel
- `default-notification-strategy` - `NotificationStrategy::Debounced` (default) or `NotificationStrategy::Immediate`
- `notification-debounce` - how long a debounced notification waits, 10 minutes by default
- `api` - `max_message_length`, the longest chat message in characters (16,000 by default), and `writes_per_minute`, how often one person may write through the chat API (60 by default; reading is not limited)
- `api.middleware` - middleware added to every chat API route (see [Chat API Middleware](#chat-api-middleware))
- `scenarios.environments` - where demo scenarios may be seeded (see [Seeding Demo Scenarios](#seeding-demo-scenarios))
- `notification-max-events` - the most activities one notification lists, 10 by default

The `levels` key is a placeholder and isn't read by the package.

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

For each resource you can easily overwrite its label, navigation group, sort, navigation icon, parent item, sub-navigation position and cluster:

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
            navigationParentItem: null,
            subNavigationPosition: null,
            cluster: null,
        );
    }
}
```

The ticket list shows only **All Tickets** and **My Tickets** tabs, in both organization and receiving panels. Use the **Overdue** table filter to narrow either list. Existing direct links to sent escalation lists remain supported, but those lists have no entries in the tab bar.

### Overdue tickets

`Ticket::query()->overdue()` finds open tickets whose current **Waiting on** is support and whose last requester message is older than the threshold. Support updates, internal notes and `TurnChanged` history do not reset that message clock. Tickets without a requester message are excluded.

The default is **1 business day**, meaning 24 local weekday hours. Saturday and Sunday pause the clock; holidays and office hours are not excluded. A Friday 10 a.m. message becomes overdue after Monday 10 a.m., including across daylight-saving changes. Equality at the threshold is not overdue.

Configure it in `config/padmission-tickets.php`:

```php
'overdue' => [
    'business_days' => 1, // A positive whole number
    'timezone' => null,  // Or a timezone such as 'America/New_York' for every ticket
],
```

With tenancy enabled, the package reads the `timezone` column on the configured `tenancy_model` and applies each organization's local cutoff, including on panels spanning organizations. A missing column, unavailable model, missing organization, empty or invalid timezone, or ticket without an organization uses `app.timezone`. This is independent of the viewer's display timezone. Host ticket and activity scopes remain in effect. The query uses the latest requester message in SQL, with an index for this lookup; it does not load tickets to calculate their ages. Run the host's migrations to add the requester-message index. Hosts with `run_migrations` set to `false` need a host migration creating `ticket_activities_requester_age_index` on `(ticket_id, type, sender, created_at)`; the package migration does not run automatically there.

## Widgets

This package comes with multiple Filament widgets that can be added to your dashboard:

- **OpenTicketsWidget** - Shows count of open tickets
- **OpenSupporterTickets** - Shows tickets assigned to supporters
- **TicketCloseTimeWidget** - Displays average ticket close times
- **OverdueTicketsWidget** - Counts overdue tickets and enables the Overdue filter on the current tab
- **TicketBurndownChartWidget** - Visualizes ticket closure trends

Widgets are not registered on the panel by default. Pass `shouldRegisterWidgets: true` to add them:

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->registerResources(shouldRegisterWidgets: true)
```

Independently of that, the `ListTickets` page shows `OpenTicketsWidget`, `OpenSupporterTickets`, `TicketCloseTimeWidget` and `OverdueTicketsWidget` in its header, to supporters only. All four cards follow the active tab, applied table filters and search through Filament's page-table integration. The overdue card uses the same `overdue()` scope as the table filter. Clicking it stays on the current tab and turns on the **Overdue** table filter, retaining other table filters and search so the list matches the card count. On a dashboard, the card opens **All Tickets** with the **Overdue** filter enabled. Away from the list, the overdue card counts the current panel's accessible tickets. It polls every 60 seconds like the other cards.

### Authorization

We define a basic policy, but you can swap it anytime with your implementation:

```php
use Padmission\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Gate;

// Define your policy, which extends from `TicketPolicy`
Gate::policy(
    Ticket::class,
    YourTicketPolicy::class
);
```

Besides `viewAny`, `view`, `create`, `update` and `delete`, the ticket UI checks these abilities, so a replacement policy must answer them too:

| Ability | Used for |
|---|---|
| `openTicketFromList` | The **New ticket** button on the ticket list |
| `manage` | Assigning and closing, one ticket or in bulk |
| `reply` | Replying as a supporter |
| `handOver` | **Hand over** / **Take over** of an escalation |
| `escalate` | Escalating a ticket |
| `reopen` | **Reopen**, and reopening by replying |

Extending `Padmission\Tickets\Policies\TicketPolicy` and overriding only what you need is the easiest way to keep them.

If no policy is registered for Statuses, Priorities, or Dispositions, their resources fall back to the `viewAny` ability on tickets. If you want specific rules for them, define a policy for those models.

### Dispositions

The package allows you to define custom dispositions for tickets. Dispositions are used to categorize tickets when they are closed. You can configure dispositions within each panel using the DispositionResource.

### Chat Widget

Users can create tickets via a chat widget. The widget provides a modern, real-time chat interface with:

- **Rich text editor** with formatting options (bold, links, lists)
- **Auto-response messages** after first user message
- **Turn management** - Shows whose turn it is to respond
- **File attachments** and **screenshots** (when configured)
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

`showChatWidget()` also takes a closure for `shouldShow` (and for `config`), evaluated on each request, so the widget can depend on the signed-in user.

`introMessage()` and `autoResponse()` are optional. Without them, or when a closure returns `null`, the widget uses the `defaults` in the package's `chat` translations. `ChatWidgetConfig::defaultIntroMessage()` and `ChatWidgetConfig::defaultAutoResponse()` return those defaults, for example to show as the placeholder of a settings field.

The widget is added to the end of every page of the panel through Filament's `BODY_END` render hook. There is no standalone Blade component: the view (`padmission-tickets::filament.chat-widget`) reads its configuration from `TicketPlugin::get()`, so it needs a panel with the plugin as the current or default panel.

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

The code sign-in routes (`/padmission-tickets/api/otp-request` and `otp-verify`) exist only when some panel's chat widget allows email authentication when the routes load, and answer 404 whenever none allows it at request time. With `allow` as a closure, it is evaluated at both moments.

A code skips the password, its second factor and the panel's login, so it is never sent to:
- an account with Filament multi-factor authentication set up (`HasAppAuthentication` with a secret, or `HasEmailAuthentication` turned on)
- a supporter on any ticket panel, which covers staff and global admins whose panels list them as supporters
- someone the panel refuses (`FilamentUser::canAccessPanel()` false, as for a deactivated user), unless `allowGuests` is on
- anyone your own rule turns away:

```php
ChatWidgetConfig::make()
    ->allowEmailAuthentication()
    ->allowEmailAuthenticationFor(fn (User $user): bool => $user->is_active && ! $user->isGlobalAdmin())
```

Those accounts sign in with their password instead. The request is answered the same way for them as for an address with no account, so the form never tells anyone which emails exist.

**Features:**
- Requests limited to one code a minute per email address and five a minute per client IP, counted before the address is looked up
- Guesses limited to ten a minute per client IP; a code is thrown away after five wrong guesses
- Configurable OTP expiration time
- Session-based authentication for verified users, with a new session ID on success; a code never stands in for someone already signed in

> **Known limitation: email-code sign-in doesn't work yet, so don't turn it on.**
>
> - **Sessions fail after the code is verified.** Laravel's middleware priority runs `Authenticate` before `AuthenticateGuests`, so the code's user is never restored in time. Every ticket API call after verifying returns 401, while the widget wrongly shows the guest as signed in.
> - **Code sessions are never checked again.** `AuthenticateGuests` and `TicketAuth::getUserId()` don't re-check an existing code session against `allowEmailAuthenticationFor()` (the package's `EmailAuthentication::admits()`), `canAccessPanel()`, multi-factor authentication, staff status, or whether the user still exists. Someone deactivated or made staff after signing in would keep their session until it expired.
> - **Verifying tells which emails have accounts.** For an unknown or refused email the request stores no code, so a wrong code on verify answers 410 (expired), while an eligible account answers 401 (invalid). The package's `OtpNotification` also isn't queued, so only eligible addresses take the time to send mail. Store a decoy code for those emails and queue the notification.
> - **The guess and request limits can be worked around.** The five-guess counter is plain session data with no lock, so parallel verify requests, or a replayed session cookie with the `cookie` driver, reset it. Keep an atomic failure counter in the cache keyed by the matched user. The per-address request limit is keyed on the lowercased email, but MySQL's `utf8mb4_unicode_ci` ignores accents, so each accented spelling of one inbox gets its own allowance. Key it on the matched user's ID after the lookup. Since that limit is per address, anyone can also keep a victim's limit full so they never get a code.
> - **Both must be fixed in the same change.** Fixing the middleware order alone would make the second gap live. Tests for the re-check are ready, but skipped, in `tests/Feature/Http/Middleware/CodeSessionRecheckTest.php`.
>
> Until then, hosts shouldn't enable `allowEmailAuthentication()`.

#### Documentation URL

You can add a button to the chat widget that opens your documentation in a new tab using `->documentationUrl()`.

```php
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->showChatWidget(
        config: ChatWidgetConfig::make()->documentationUrl(url('/docs'))
    );
```


#### File Uploads and Screenshots

If you want to allow users to upload files you can use the `->allowFileUploads()` method on the `ChatWidgetConfig`. It requires `league/flysystem-aws-s3-v3` and throws an exception when that package isn't installed. The second argument is the largest file allowed, in bytes (10 MB by default):

```php
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->showChatWidget(config: ChatWidgetConfig::make()
        ->allowFileUploads(maxFileSize: 20 * 1024 * 1024)
        ->allowScreenshots()
    );
```

`allowScreenshots()` adds a button that captures the user's screen and attaches it. It only shows when file uploads are allowed and the browser supports screen capture.

The server holds uploads to the same maximum (the ticket's panel's `maxFileSize`) and to the types in the `attachments.allowed_mime_types` config: images, videos and PDFs, and everyday office and text documents (Word, Excel, OpenDocument, RTF, CSV and plain text). HTML, SVG, scripts and programs are refused. Only an image, video or PDF opens in the browser; every other file, and any file stored before with a type no longer allowed, is served as a download. That's done through the download link's response headers, which S3 honours; a local disk's temporary URLs ignore them, though Laravel still serves those files with a sandboxing Content-Security-Policy. **New ticket**'s attachments take the same types, and the chat's file picker offers them, each with its usual extension. A file's name is kept to its last part, without folders or control characters, before it names the stored file. A file's download link is signed only for someone who sees the message it was sent on, so a requester never gets one for a file on an internal note.

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

A panel that has to see across tenants, such as a central support panel, can lift the host's tenant scope from the ticket query and from the ticket relationships:

```php
TicketPlugin::make()
    ->customizeTicketQuery(fn (Builder $query) => $query->withoutGlobalScope(TenantScope::class))
    ->modifyRelationshipScopes(fn ($relation, string $model) => $relation->withoutGlobalScope(TenantScope::class))
```

`modifyRelationshipScopes()` receives the relation (or its query) as `$relation` and the relationship name as `$model`, and should return the relation.

### Turn Management

The package automatically tracks whose "turn" it is to respond to a ticket:
- **User Turn** - Waiting for user response
- **Supporter Turn** - Waiting for support agent response

This helps support teams prioritize tickets that need attention. Turn changes are automatically logged in the activity history.

By default the sidebar badge counts the viewer's open assigned tickets. `->navigationBadgeCountsNeedsYou()` makes it count the tickets that need the viewer instead.

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

Pass a closure rather than a built `Builder`: a closure is evaluated each time, while a `Builder` built at panel registration keeps the database connection of that moment.

The closure may declare an optional `?Ticket $ticket` parameter. The package passes the ticket when it looks up supporters for a specific ticket, for example to notify supporters of an unassigned ticket. That lookup can run in a queue worker or console command where the host's tenant scope isn't bound, so in a multi-tenant app the closure **must** scope by the ticket's tenant when a ticket is given. The package's `TicketPolicy` asks the pool for the ticket it checks, so without that scope a supporter of one tenant would count as a supporter of another tenant's tickets:

```php
use Padmission\Tickets\Models\Ticket;

TicketPlugin::make()
    ->allSupportersQuery(fn (?Ticket $ticket = null) => User::role('support')
        ->when($ticket, fn ($query) => $query
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $ticket->tenant_id)))
```

If a person has several accounts and the pool keeps only one of them, `->matchSupportersBy('email')` matches supporters on that column instead of the ID, and `->currentUserAssigneeIds(fn (): array => [...])` lists the assignee IDs that count as the signed-in user (for **My Tickets**).

**Initial Assignment Query (Optional)**

Define a subset of users for automatic assignment:

```php
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role(['support', 'senior-support', 'admin']))
    ->initialAssignmentSupportersQuery(fn () => User::role('support'))
    ->assignmentStrategy(new AssignUserWithLeastTickets())
```

Automatic assignment falls back to `allSupportersQuery()` only when no initial query is set. If the initial query is set but returns nobody, the ticket stays unassigned.

Both pools are read from the panel the ticket belongs to (its `panel` column), which for a ticket sent elsewhere with `targetPanel()` or escalated is the receiving panel. An `initialAssignmentSupportersQuery()` on the sending panel is ignored for those tickets.

The **strategy**, however, is read from the panel the ticket is created on. A sending panel without an `assignmentStrategy()` leaves its tickets unassigned.

#### Assignment Strategies

**No strategy (Default)**

Without `->assignmentStrategy()`, tickets are left unassigned, and **New ticket** doesn't offer "Assign automatically". `DoNotAssign` does the same explicitly:

```php
use Padmission\Tickets\AssignmentStrategies\DoNotAssign;

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

Assigns to a specific user, who must be in the target panel's `allSupportersQuery()`:

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
// Main support panel - manages all tickets and decides who gets them
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
                // Needed for tickets created here to be auto-assigned;
                // the support panel's tier-1 pool is used.
                ->assignmentStrategy(new AssignUserWithLeastTickets())
                ->showChatWidget()
                ->registerResources(false) // Don't show management UI here
        );
}

// Enterprise panel - also routes to support, but assigns every ticket to one lead
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('enterprise')
        ->plugin(
            TicketPlugin::make()
                ->targetPanel('support')
                ->assignmentStrategy(new AssignDefaultUser(
                    fn () => User::role('enterprise-lead')->first()->id
                ))
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
        // Uses the ticket's panel: its initialAssignmentSupportersQuery, or allSupportersQuery when none is set
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

### New Ticket

Supporters open tickets from the ticket list with **New ticket**. It needs the `openTicketFromList` ability, which by default means being in the panel's `allSupportersQuery()`.

**On an organization's panel** (`StartTicketAction`) the slide-over offers two choices:

- **For someone in the organization** - pick the requester, then assign it to yourself, to a colleague, or automatically (only offered when the panel has an assignment strategy). The ticket waits on the support side.
- **A question for the support team** - when the panel escalates (see below), this opens a ticket of the supporter's own in the team's panel.

`requestersQuery()` sets who the requester may be. By default it is every user the host's own scopes let the panel see, which for a tenant panel should be the tenant's users. The search matches `name` and `email` and shows the first 50.

```php
TicketPlugin::make()
    ->requestersQuery(fn (): Builder => User::query()->whereNull('deactivated_at'))
```

**On a panel that receives escalations** (`OpenTicketForContactAction`), **New ticket** logs a question that an organization's supporter asked some other way, such as by phone: pick the organization, then the contact from that panel's supporters. The ticket is opened as that contact's escalation, assigned to you. It's off by default on such a panel, so turn it on with `startsTickets()`, and say which organizations may be picked when tickets belong to a tenant:

```php
TicketPlugin::make()
    ->startsTickets()
    ->ticketTenantsQuery(fn (): Builder => Tenant::query()->where('active', true))
```

The organization list opens with the first 50, searched and sorted by the tenant's `name` column. Without `ticketTenantsQuery()` it is empty. `startsTickets(false)` hides **New ticket** on any panel.

When tenancy is enabled, a status filter whose scoped options span several organizations offers each display name once and matches that status in every organization. A panel scoped to one organization keeps its status ID filter. The host's ticket and relationship scopes still control which organizations it can see.

### Escalations

An organization's panel can escalate a ticket to a central support panel. The escalation is a new ticket in the support panel, linked to the organization's ticket (the "original"). The organization's supporter talks to the support team on the escalation, and to their requester on the original.

```php
// Organization's panel
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('support'))
    ->registerResources()
    ->allowLinkedTicketsTo(['support'])
    // Escalations are created here, so this strategy assigns them,
    // drawing from the support panel's pool.
    ->assignmentStrategy(new AssignUserWithLeastTickets())

// Support panel
TicketPlugin::make()
    ->allSupportersQuery(fn () => User::role('padmission-staff'))
    ->initialAssignmentSupportersQuery(fn () => User::role('tier-1-support'))
    ->registerResources()
    ->supportTeamName('Padmission')
```

`allowLinkedTicketsTo()` takes the IDs of the panels this panel may escalate to. With more than one, the supporter picks the team. `supportTeamName()` (a string or closure) names that team in the UI, for example "Escalate to Padmission", instead of generic wording.

What the organization's panel gets:

- **Escalate Ticket** (or **Escalate to _team_**) on an open ticket, which opens the escalation with a subject and message for the other team. Once the escalation closes, the same action escalates again.
- **Add to escalation** on an open ticket, to link it to an escalation the organization already has open instead of opening another. It only shows when there is one.
- **Remove from escalation** on an original, to unlink it.
- Existing direct links to the organization's escalations and the viewer's own continue to work; only **All Tickets** and **My Tickets** appear in the tab bar.
- On an escalation, a list of its originals that says who owes whom a reply, with `+` to link another and `×` to take one out.
- **Hand over** on an escalation the viewer owns, to give it to a colleague, and **Take over** for anyone else on the team. The other team's replies, access to the escalation and the viewer's own escalation list all follow the new owner. Only the two people it moved between are notified.
- **Close escalation** on the viewer's own escalation, without a disposition, once the other team's part is done.

What the support panel gets:

- The escalation in its list, assigned from its own pool by the organization panel's strategy (see [Ticket Assignment](#ticket-assignment)).
- An **Original ticket** panel beside the escalation, showing the original's conversation, its title and its people. With several originals, it says which one of how many is showing, and they can be stepped through or picked from a list.

How the original is shown beside an escalation is set on the viewing panel:

```php
TicketPlugin::make()
    ->linkedConversationView(TicketPlugin::LINKED_VIEW_DRAWER) // LINKED_VIEW_BESIDE (default), LINKED_VIEW_MODAL or LINKED_VIEW_DRAWER
    ->pinLinkedConversation()
```

### Reopen

A closed ticket reopens in two ways:

- **Reopen** (`ReopenTicketAction`) lets staff of the ticket's own panel reopen it at any time without writing to it.
- **Replying** reopens it. The requester can do this within the reopen window, the person handling an escalation at any time. After the window, a requester's reply starts a new ticket that links back to the old one.

The window is 30 days by default:

```php
TicketPlugin::make()
    ->reopenWindowDays(14)
```

A reopen notifies the supporter by default (`TicketReopenedEvent`).

### Bulk Actions

The ticket list's bulk actions are:

- **Assign** - assign the selected tickets to someone from `allSupportersQuery()`. Tickets the viewer may not `manage` are skipped.
- **Close** - close each selected ticket as its own Close dialog would, skipping those already closed or that the viewer may not close. The disposition is picked by name, since the selection can span organizations that each keep their own.
- **Delete** - soft-deletes the tickets the viewer may `delete`.

They're hidden in the sent escalation lists.

A single open ticket on its own panel also has **Edit** (subject, assignee, status and priority), **Assign** / **Reassign**, **Close** and **Delete** (a soft delete).

### Person Actions

`personActionsUsing()` adds your own actions, such as Impersonate, beside each person a ticket names: beside **Requested by** in the sidebar, and beside the people in the **Original ticket** panel. They're drawn as small icon buttons right after the name, with the action's label as the tooltip. An action without an icon gets a sign-in arrow.

The closure receives `$person` and `$ticket` and returns a list of Filament actions. Anything else in the list is dropped. The visibility and authorization rules stay yours:

```php
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Models\Ticket;

TicketPlugin::make()
    ->personActionsUsing(fn (Model $person, Ticket $ticket): array => $person instanceof User ? [
        Action::make('impersonate')
            ->label('Impersonate')
            ->icon(Heroicon::OutlinedFingerPrint)
            ->url(fn (): string => route('impersonate', $person))
            ->visible(fn (): bool => auth()->user()->can('impersonate', $person)),
    ] : [])
```

### Chat API Middleware

The chat's API (`/padmission-tickets/api/tickets/...`) runs outside any panel. Neither a panel's middleware nor any a host adds to its pages runs on it, so the package adds the middleware listed in `api.middleware` after its own. The default is `AuthenticateChatSession`: Laravel's `AuthenticateSession`, answering 401 instead of redirecting. It ends a session that a password change logged out elsewhere. Add your own checks, such as one that a user is still active, so a deactivated user with an open tab can't keep reading and replying:

```php
// config/padmission-tickets.php
'api' => [
    'middleware' => [
        \Padmission\Tickets\Http\Middleware\AuthenticateChatSession::class,
        \App\Http\Middleware\CheckUser::class,
    ],
],
```

The routes read the list when they load, so run `php artisan route:cache` again after changing it. The read endpoints still never save the session.

### Disabling Replies

`replyDisabledUsing()` stops someone writing in the chat for a reason of your own, such as a session that may only look. The closure receives `$ticket` and `$user` and returns `null` when they may reply, or the reason they may not:

```php
use Padmission\Tickets\Models\Ticket;

TicketPlugin::make()
    ->replyDisabledUsing(fn (Ticket $ticket, User $user): ?string => session()->has('read_only')
        ? 'You are only viewing this ticket, so you can\'t reply.'
        : null)
```

While it returns a reason, the chat's reply box stays in place but is greyed out, with the reason where the placeholder would be, and nothing can be typed, attached or sent. The server refuses a message or an attachment upload with a 403 whose JSON gives the reason under `message`, before a reply could reopen a closed ticket. The chat asks the panel it was opened in (its API runs outside any panel, so the chat names the panel in a header, which is ignored when it names a panel the user may not enter) and the ticket's own panel, and refuses if either gives a reason, so set it on every panel where it applies. It covers every way of writing in the chat: a message or upload, a new ticket or follow-up from the chat (asked of the panel the ticket goes to), the message Escalate and Add to escalation offer to send the requester, the first message of a ticket started with **New ticket** or opened for a contact, and Resolve in the copilot panel. The ticket page's other actions keep their own authorization.

### Display and UI Options

```php
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;

TicketPlugin::make()
    // Ticket times: timezone (defaults to Filament's) and the date-time format
    ->displayTimezone(fn (): string => auth()->user()->timezone)
    ->dateTimeDisplayFormat('d.m.Y H:i:s')

    // Extra lines under a person's name, e.g. their roles (a string or a list)
    ->describeUsersUsing(fn (Model $user, Ticket $ticket): array => $user->roles->pluck('name')->all())
    // Where a ticket came from, shown on the ticket
    ->describeTicketOriginUsing(fn (Ticket $ticket): ?string => $ticket->tenant?->name)
    // Help text for who can be assigned
    ->assignableUsersDescription('Anyone with the Support role')

    // Extra infolist components in the ticket's details, and extra list columns
    ->additionalTicketDetails(fn (): array => [TextEntry::make('tenant.name')])
    ->additionalTableColumns(fn (): array => [TextColumn::make('tenant.name')])

    // How field help shows: FIELD_HELP_TOOLTIP (default), FIELD_HELP_INLINE or FIELD_HELP_SUMMARY
    ->fieldHelp(TicketPlugin::FIELD_HELP_INLINE)
    // "Send as update" in the chat: KEEP_WAITING_BUTTON (default) or KEEP_WAITING_CHECKBOX
    ->keepWaitingStyle(TicketPlugin::KEEP_WAITING_CHECKBOX)
```

`TicketPlugin::get()` returns the plugin of the current (or given) panel. `TicketPlugin::find($panelId)` returns `null` instead of failing when that panel doesn't have the plugin, for example in a queue worker that doesn't register it.

### Notification Configuration Per Panel

The package supports granular control over who receives notifications based on event type and actor role. You can configure different notification rules for each Filament panel using a fluent API.

`NotificationConfiguration::make()` starts with the defaults below, and each `->on()` replaces the rule for one event.

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
- `NotificationRecipient::Supporter` - Notify the assigned supporter only. On an unassigned ticket, everyone in the ticket panel's `allSupportersQuery()` is notified instead, apart from the actor and the submitter.
- `NotificationRecipient::Both` - Notify both user and supporter
- `NotificationRecipient::None` - Don't send any notifications

For each event type, you define a closure that receives the trigger type and returns who should be notified. This allows for flexible notification rules based on who initiated the action.

When a debounced notification is finally sent, the job checks that the ticket still concerns the recipient. If the ticket is now someone else's, or the recipient can no longer view it, nothing is sent.

#### Default Behavior

The package provides sensible defaults if no configuration is provided:

**Ticket Created**
- User-triggered: Notifies user only
- Supporter-triggered: Notifies both user and supporter

**Ticket Assigned**
- Either trigger: Notifies supporter

**Ticket Activity** (messages, comments)
- User-triggered: Notifies supporter only
- Supporter-triggered: Notifies user only

**Ticket Closed**
- User-triggered: Notifies supporter only
- Supporter-triggered: Notifies user only

**Ticket Reopened**
- Either trigger: Notifies supporter

**Ticket Handed Over** (`TicketHandedOverEvent`)
- Not configurable through `on()`. The two people the escalation moved between are notified, apart from whoever did it.

The notification class sent for each event is set in the config's `notifications` map.

#### Delivery Channels

Notifications are emailed by default. To also show them in Filament's notification panel, add the `database` channel:

```php
// config/padmission-tickets.php
'notification-channels' => ['mail', 'database'],
```

The host needs Laravel's `notifications` table and `->databaseNotifications()` on the panels its ticket users work in. Every channel of one send carries the same batch of unread activity.

#### Delivery Strategy

By default notifications are debounced: activity on a ticket is gathered for `notification-debounce` seconds (10 minutes) and sent as one notification of at most `notification-max-events` activities. Change the default with `default-notification-strategy`, or per user with a `ticketNotificationStrategy()` method on the user model:

```php
use Padmission\Tickets\Enums\NotificationStrategy;

public function ticketNotificationStrategy(): NotificationStrategy
{
    return $this->wants_instant_emails
        ? NotificationStrategy::Immediate
        : NotificationStrategy::Debounced;
}
```

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
```

### Activity Tracking

All ticket changes are automatically tracked in the activity log (`Padmission\Tickets\Enums\ActivityType`):

- **Message** - Regular ticket messages
- **Internal Message** - Internal notes not visible to end users
- **Opened** - Ticket creation
- **Opened For** - A supporter opened the ticket for someone else
- **Asked Directly** - The support team logged a question an organization's supporter asked some other way
- **Priority Changed** - Priority modifications
- **Status Changed** - Status updates
- **Subject Changed** - Subject edits
- **Assignee Changed** - Assignment changes
- **Turn Changed** - Turn ownership changes
- **Closed** - Ticket closure with disposition
- **Reopened** - Ticket reopened
- **Follows Up** - A new ticket that follows up a closed one
- **Escalated** - The ticket was escalated
- **Added To Escalation** / **Removed From Escalation** - On the original, when it's linked to or unlinked from an escalation
- **Original Added** / **Original Removed** - The same change, recorded on the escalation
- **Handed Over** - An escalation moved to another person

Activities include:
- User who made the change
- Timestamp
- Previous and new values (where applicable)
- Soft delete support for audit trails

### File Attachments

Attachments are stored directly on a filesystem disk, not through a media library. The browser uploads each file straight to the disk through a presigned URL, so the disk must be S3-compatible (`league/flysystem-aws-s3-v3`). Configure the disks in your configuration:

```php
// config/padmission-tickets.php
'attachments' => [
    'disk' => env('MEDIA_DISK', 's3'),
    'preview_disk' => env('MEDIA_DISK', 's3'),
    'allowed_mime_types' => ['image/jpeg', 'image/png', /* ... */ 'application/pdf', 'text/plain'],
],
```

`disk` holds the files and `preview_disk` the image previews made from them. `allowed_mime_types` lists the types an attachment may have (see [File Uploads and Screenshots](#file-uploads-and-screenshots)); a presigned upload signs neither the type nor the size it is sent with, so the server checks the type and size asked for, compares the stored file's size when the message is sent, and names the type each file is served as. Files can be attached to messages through the chat widget and **New ticket**. Subjects and attachment names are checked with the `Padmission\Tickets\Rules\PlainText` rule, which refuses HTML tags and script or data URLs. A message's HTML is cleaned by `Padmission\Tickets\Support\MessageHtml::sanitize()` on every path that stores one, keeping only what the chat's composer can write (paragraphs, bold, italics, lists, quotes, code and links to safe addresses), and the chat cleans it again where it draws it, so a row stored before can't run script either. A host that writes messages of its own should pass them through it too.

### Copilot Panel

The package registers a Livewire component, `padmission-tickets-copilot-panel`, with a compact ticket list, conversation and new-ticket form for embedding in another UI, such as an AI assistant's side panel:

```blade
<livewire:padmission-tickets-copilot-panel :initial-ticket-id="$ticketId" />
```

### Seeding Demo Scenarios

`Padmission\Tickets\Seeding\TicketScenarios` fills a preview or QA environment with tickets worth opening: a real back-and-forth, closed and reopened tickets, escalations and so on. Its rows are the ones the live flows write (history, turn, status, assignee, seen pointers), backdated over the past days, and it notifies nobody.

```php
use Padmission\Tickets\Seeding\TicketScenarios;

$scenarios = TicketScenarios::make(
    panelId: 'app',
    tenant: $organization,            // a model, a key, or null without tenancy
    requesters: [$alice, $bob],       // people who ask from the chat, none of them supporters
    supporters: [$maria],             // the panel's supporter pool; the first owns escalations
    colleagues: [$dev],               // optional: someone on the same team, to hand an escalation to
)->escalatesTo('admin', [$kevin]);    // optional: the escalation target and who answers there

$scenarios->all();                    // every scenario once, keyed by name

$scenarios->conversation();           // or one at a time; each returns its Ticket
$scenarios->escalation(originals: 3, closedOriginals: 1);
```

| Method | Leaves |
|--------|--------|
| `conversation()` | Five messages from both sides, waiting on support |
| `waitingOnRequester()` | Support replied last, waiting on the requester |
| `closed()` | Closed with the panel's first disposition |
| `reopened()` | Closed, then reopened by the requester's reply |
| `unassigned()` | A new question nobody has |
| `assignedToNonSupporter(?Model $assignee = null)` | Assigned outside the supporter pool, so it asks for a new assignee |
| `openedFor()` | Opened by a supporter for a requester with New ticket |
| `escalation(int $originals = 2, int $closedOriginals = 0, bool $answered = false)` | An escalation linking its originals (as `childTickets`), the last `closedOriginals` closed; `answered` leaves the other team's answer as the last word |
| `directQuestion()` | A question asked straight of the other team, with no originals |
| `handedOver()` | An escalation handed from the first supporter to a colleague |

Each scenario is marked in `data.seeded_scenario` and found again by it, so seeding twice returns the first run's tickets instead of adding more. Every method takes an optional `key:` to seed a second one. The escalation scenarios need `escalatesTo()`, and `handedOver()` a colleague or a second supporter; `all()` skips what it can't seed. Statuses, priorities and dispositions must exist first (`php artisan tickets:seed --only=statuses,priorities,dispositions`); the command below seeds them itself.

To add scenarios of your own, extend the class and build them from its protected steps (`seedOnce()`, `openFromChat()`, `message()`, `close()`, `reopenByReply()`, `reassign()`, `escalate()`, `addOriginal()`, `handOver()`, `startAt()`, `later()`).

Without a host seeder, `php artisan tickets:seed --only=scenarios` seeds them for each panel that no other panel escalates to, from its own `allSupportersQuery()` and the users of its `requestersQuery()` outside it, escalating to the panel's first `allowLinkedTicketsTo()` panel.

Scenarios put made-up conversations under real people's names, so they are seeded only in the environments the `scenarios.environments` config lists: `local`, `testing`, `test`, `staging`, `preview` and `qa` by default. Anywhere else, production above all, `TicketScenarios::make()`, `TicketScenarioSeeder` and `tickets:seed --only=scenarios` refuse before writing anything.

```php
// config/padmission-tickets.php
'scenarios' => [
    'environments' => ['local', 'staging', 'demo'],
],
```

## Customization

### Custom Models

You can extend the package models with your own:

```php
// config/padmission-tickets.php
'models' => [
    Illuminate\Contracts\Auth\Authenticatable::class => App\Models\User::class,
    Padmission\Tickets\Models\Ticket::class => App\Models\Ticket::class,
    Padmission\Tickets\Models\TicketActivity::class => App\Models\TicketActivity::class,
    // ... other models
],
```

The `Authenticatable::class` entry is the user model the package resolves requesters, supporters and notification recipients from. Your custom models should extend the package models to ensure compatibility.

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

`NotificationJob` offers these override points:

- `initializeJob(Authenticatable $user, Ticket $model): void` - called at the end of the constructor
- `stillConcerns(Model $user, Ticket $record): bool` - the check made just before sending
- `sendNotification(Model $user, Ticket $record, string $notificationClass): void` - the send itself
- `uniqueId(): string` - the key debounced jobs are coalesced on

`getUserId()`, `getTicketClass()` and `getTicketKey()` return what the job was queued for. For example, to add a tenant to the job:

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;
use Throwable;

class CustomNotificationJob extends NotificationJob
{
    public int|string|null $tenantId = null;

    protected function initializeJob(Authenticatable $user, Ticket $model): void
    {
        $this->tenantId = $model->tenant_id;

        // Set custom queue, delay, etc.
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return parent::uniqueId().'-tenant-'.$this->tenantId;
    }

    protected function stillConcerns(Model $user, Ticket $record): bool
    {
        return parent::stillConcerns($user, $record)
            && $user->tenant_id === $record->tenant_id;
    }

    // Laravel's own hook for a failed queued job
    public function failed(Throwable $exception): void
    {
        Log::error('Notification job failed', [
            'tenant_id' => $this->tenantId,
            'ticket_id' => $this->getTicketKey(),
            'user_id' => $this->getUserId(),
            'error' => $exception->getMessage(),
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

Debug your queries to see what users are being returned. Check the panel the ticket goes to, not the one it was sent from:

```php
// Test all supporters query
$query = app()->call(TicketPlugin::get('support')->getAllSupportersQuery());
dd($query->count(), $query->pluck('name', 'id'));

// Test initial assignment query
$query = app()->call(TicketPlugin::get('support')->getInitialAssignmentSupportersQuery());
dd($query->count(), $query->pluck('name', 'id'));
```

If the initial assignment query is set and returns nobody, tickets stay unassigned: there's no fall-back to `allSupportersQuery()` in that case.

### Tickets Going to Wrong Panel

Ensure your target panel ID matches exactly:

```php
// Panel ID must match exactly (case-sensitive)
->targetPanel('support') // Not 'Support' or 'SUPPORT'
```

### Assignment Strategy Not Working

1. Ensure the User model has the `HasTickets` trait
2. Check that your queries return users
3. Verify the assignment strategy is configured on the panel the ticket is **created** on
4. Check Laravel logs for exceptions

## Support

For additional support:
- Contact support at [hello@padmission.com](mailto:hello@padmission.com)

## Credits

- [Padmission](https://github.com/Padmission)
- [All Contributors](../../contributors)

## License

The Tickets package is a private, paid package. All rights reserved. Unauthorized distribution, modification, or use is strictly prohibited.
