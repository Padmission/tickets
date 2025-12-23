<?php

use Carbon\CarbonInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Events;
use Padmission\Tickets\Models;
use Padmission\Tickets\Notifications;

return [
    'run_migrations' => true,

    /**
     * Swap package models (left) with your own model (right).
     * Your models should extend the package base models to inherit observers automatically.
     * Example: class CustomTicket extends \Padmission\Tickets\Models\Ticket { }
     *
     * @var array<class-string, class-string>
     */
    'models' => [
        Authenticatable::class => App\Models\User::class,
        Models\Ticket::class => Models\Ticket::class,
        Models\TicketActivity::class => Models\TicketActivity::class,
        Models\TicketAttachment::class => Models\TicketAttachment::class,
        Models\TicketDisposition::class => Models\TicketDisposition::class,
        Models\TicketNotification::class => Models\TicketNotification::class,
        Models\TicketPriority::class => Models\TicketPriority::class,
        Models\TicketStatus::class => Models\TicketStatus::class,
    ],

    /**
     * Swap package job classes (left) with your own job classes (right).
     * Your jobs should extend the package base jobs to inherit the same behavior.
     * Example: class CustomNotificationJob extends \Padmission\Tickets\Jobs\NotificationJob { }
     *
     * @var array<class-string, class-string>
     */
    'jobs' => [
        Padmission\Tickets\Jobs\NotificationJob::class => Padmission\Tickets\Jobs\NotificationJob::class,
    ],

    'tenancy' => [
        'enabled' => false,
        'tenancy_model' => App\Models\Tenant::class,
    ],

    /**
     * Attachment storage configuration
     *
     * Settings for file storage and media handling.
     *
     * @var array<string, string|null>
     */
    'attachments' => [
        /**
         * The disk on which to store added files and derived images by default.
         * Choose one or more of the disks configured in config/filesystems.php.
         * Typically, this should match the default Spatie Media library disk name.
         *
         * If null, it will default to filament.default_filesystem_disk
         *
         * @var string|null
         */
        'preview_disk' => env('MEDIA_DISK', 's3'),
        'disk' => env('MEDIA_DISK', 's3'),
    ],

    /**
     * Notification configuration
     *
     * @var array<string, class-string|null>
     */
    'notifications' => [
        Events\TicketCreatedEvent::class => Notifications\TicketNotification::class,
        Events\TicketActivityEvent::class => Notifications\TicketNotification::class,
        Events\TicketAssignedEvent::class => Notifications\TicketNotification::class,
        Events\TicketClosedEvent::class => Notifications\TicketNotification::class,
    ],

    /**
     * Default notification strategy when user doesn't define one
     * Options: Padmission\Tickets\Enums\NotificationStrategy::Immediate, Padmission\Tickets\Enums\NotificationStrategy::Debounced
     *
     * @var string
     */
    'default-notification-strategy' => Padmission\Tickets\Enums\NotificationStrategy::Debounced,

    /**
     * Debounce time in seconds for grouped notifications
     *
     * @var int
     */
    'notification-debounce' => CarbonInterval::minutes(10)->totalSeconds,

    /**
     * Maximum number of activities to include in a single notification
     *
     * @var int
     */
    'notification-max-events' => 10,
];
