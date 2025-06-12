<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;

return [
    'run_migrations' => true,

    /**
     * Swap package models (left) with your own model (right).
     * Your models should extend the package models to ensure type safety.
     *
     * @var array<class-string, class-string>
     */
    'models' => [
        Authenticatable::class => App\Models\User::class,
        Padmission\Tickets\Models\Ticket::class => Padmission\Tickets\Models\Ticket::class,
        Padmission\Tickets\Models\TicketActivity::class => Padmission\Tickets\Models\TicketActivity::class,
        Padmission\Tickets\Models\TicketNotification::class => Padmission\Tickets\Models\TicketNotification::class,
        Padmission\Tickets\Models\TicketPriority::class => Padmission\Tickets\Models\TicketPriority::class,
        Padmission\Tickets\Models\TicketStatus::class => Padmission\Tickets\Models\TicketStatus::class,
    ],

    'tenancy' => [
        'enabled' => false,
        'foreign_key' => 'tenant_id',
        'foreign_key_type' => 'id', // options: 'id', 'ulid', 'uuid'
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
        'storage' => env('MEDIA_DISK', 's3'),
    ],

    'event-listeners' => [
        \Padmission\Tickets\Events\TicketActivity::class => [
            \Padmission\Tickets\Listeners\TicketActivityListener::class,
        ],
        \Padmission\Tickets\Events\TicketAssigned::class => [
            \Padmission\Tickets\Listeners\TicketAssignedListener::class,
        ],
        \Padmission\Tickets\Events\TicketClosed::class => [
            \Padmission\Tickets\Listeners\TicketClosedListener::class,
        ],
        \Padmission\Tickets\Events\TicketCreated::class => [
            \Padmission\Tickets\Listeners\TicketCreatedListener::class,
            \Padmission\Tickets\Listeners\TicketCreatedInitialMessageListener::class,
        ],
    ],
    'notifications' => [
        'activity' => Padmission\Tickets\Notifications\TicketActivityNotification::class,
        'assigned' => Padmission\Tickets\Notifications\TicketAssignedNotification::class,
        'closed' => Padmission\Tickets\Notifications\TicketClosedNotification::class,
        'created' => Padmission\Tickets\Notifications\TicketCreatedNotification::class,
    ],
];
