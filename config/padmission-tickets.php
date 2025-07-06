<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;

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
        Padmission\Tickets\Models\Ticket::class => Padmission\Tickets\Models\Ticket::class,
        Padmission\Tickets\Models\TicketActivity::class => Padmission\Tickets\Models\TicketActivity::class,
        Padmission\Tickets\Models\TicketAttachment::class => Padmission\Tickets\Models\TicketAttachment::class,
        Padmission\Tickets\Models\TicketDisposition::class => Padmission\Tickets\Models\TicketDisposition::class,
        Padmission\Tickets\Models\TicketNotification::class => Padmission\Tickets\Models\TicketNotification::class,
        Padmission\Tickets\Models\TicketPriority::class => Padmission\Tickets\Models\TicketPriority::class,
        Padmission\Tickets\Models\TicketStatus::class => Padmission\Tickets\Models\TicketStatus::class,
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

    'levels' => [
        // 'default' => fn () => __('padmission-tickets::tickets.levels.default'),
        // 'escalated' => fn () => __('padmission-tickets::tickets.levels.escalated'),
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
    ],

    'event-listeners' => [
        \Padmission\Tickets\Events\TicketActivityEvent::class => [
            \Padmission\Tickets\Listeners\TicketNotificationListener::class,
        ],
        \Padmission\Tickets\Events\TicketAssignedEvent::class => [
            \Padmission\Tickets\Listeners\TicketNotificationListener::class,
        ],
        \Padmission\Tickets\Events\TicketClosedEvent::class => [
            \Padmission\Tickets\Listeners\TicketNotificationListener::class,
        ],
        \Padmission\Tickets\Events\TicketCreatedEvent::class => [
            \Padmission\Tickets\Listeners\TicketNotificationListener::class,
        ],
    ],

    /**
     * Notification configuration
     *
     * @var array<string, class-string|null>
     */
    'notifications' => [
        'activity' => Padmission\Tickets\Notifications\TicketNotification::class,
        'assigned' => Padmission\Tickets\Notifications\TicketNotification::class,
        'closed' => Padmission\Tickets\Notifications\TicketNotification::class,
        'created' => Padmission\Tickets\Notifications\TicketNotification::class,
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
    'notification-debounce' => 300,

    /**
     * Maximum days to look back for activities in notification emails
     *
     * @var int
     */
    'notification-max-days' => 10,

    /**
     * Maximum number of activities to include in a single notification
     *
     * @var int
     */
    'notification-max-events' => 10,

    /**
     * Cache TTL for notification job deduplication (seconds)
     *
     * @var int
     */
    'notification-cache-ttl' => 300,
];
