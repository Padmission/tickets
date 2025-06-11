# Padmission Tickets

[![Premium Package](https://img.shields.io/badge/package-premium-gold?style=flat-square)](https://tickets.padmission.com)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D10.0-red?style=flat-square)](composer.json)
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

## Configuration

### Resources

The package comes with a set of Filament resources to manage tickets. If you want to manage tickets via this Filament panel, you can use the `->registerResources()` method:

```php
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->registerResources();
```

### Disposition

The package allows you to define custom dispositions for tickets. Dispositions are used to categorize tickets when they are closed. 
You can configure dispositions within each panel using hte DispositionResource.

### Chat Widget

Users can create tickets via a chat widget. To enable the widget in a panel, use the `->showChatWidget()` method:

```php
use Padmission\Tickets\TicketPlugin;

TicketPlugin::make()
    ->showChatWidget();
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
