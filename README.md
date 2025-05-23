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

## Installation


**Step 1:** Install the package via Composer:

```
composer require padmission/tickets
```

**Step 2:** Run the migrations to set up the database tables:

```bash
php artisan migrate
```
**Step 3:** Add the plugin to your Filament panel:

```php
use Padmission\Tickets\TicketPlugin;

$panel->plugin(TicketPlugin::make());
```

### Activating Your License

For distribution we use [Satis Padmission](https://satis.padmission.com/), a private Composer repository. During the purchasing process, Lemon Squeezy will provide you with a license key that you'll need for installation.

### Step 1: Configure Composer Repository

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

### Step 2: Install the Package

Install Data Lens using Composer:

```bash
composer require padmission/tickets
```

When prompted, provide your authentication details:
- **Username**: Your email address (e.g., myname@example.com)
- **Password**: Your license key (e.g., 9f3a2e1d-5b7c-4f86-a9d0-3e1c2b4a5f8e)

## Configuration

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

The Data Lens package is a private, paid package. All rights reserved. Unauthorized distribution, modification, or use is strictly prohibited.
