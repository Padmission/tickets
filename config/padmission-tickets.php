<?php

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events;
use Padmission\Tickets\Jobs\NotificationJob;
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
        Authenticatable::class => User::class,
        Models\Ticket::class => Models\Ticket::class,
        Models\TicketActivity::class => Models\TicketActivity::class,
        Models\TicketAttachment::class => Models\TicketAttachment::class,
        Models\TicketDisposition::class => Models\TicketDisposition::class,
        Models\TicketNotification::class => Models\TicketUserState::class,
        Models\TicketPriority::class => Models\TicketPriority::class,
        Models\TicketStatus::class => Models\TicketStatus::class,
        Models\TicketUserState::class => Models\TicketUserState::class,
    ],

    /**
     * Swap package job classes (left) with your own job classes (right).
     * Your jobs should extend the package base jobs to inherit the same behavior.
     * Example: class CustomNotificationJob extends \Padmission\Tickets\Jobs\NotificationJob { }
     *
     * @var array<class-string, class-string>
     */
    'jobs' => [
        NotificationJob::class => NotificationJob::class,
    ],

    'tenancy' => [
        'enabled' => false,
        'tenancy_model' => Tenant::class,
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
        'disk' => env('MEDIA_DISK', 's3'),

        /**
         * The types a chat attachment may have: images, videos and PDFs, which
         * open in the browser, and everyday office and text documents, which
         * are only ever downloaded. A file of any other type is refused, and one
         * already stored is only ever served as a download. HTML, SVG, scripts
         * and programs are left out on purpose, since a browser or the reader's
         * computer runs what they carry.
         *
         * @var list<string>
         */
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/heic',
            'image/heif',
            'image/avif',
            'video/mp4',
            'video/quicktime',
            'video/webm',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/rtf',
            'text/rtf',
            'text/csv',
            'text/plain',
        ],
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
        Events\TicketHandedOverEvent::class => Notifications\TicketNotification::class,
        Events\TicketReopenedEvent::class => Notifications\TicketNotification::class,
    ],

    /**
     * Channels ticket notifications are delivered through.
     * Add 'database' to also show them in Filament's notification panel; the
     * host needs the `notifications` table and ->databaseNotifications() on
     * its panels.
     *
     * @var array<int, string>
     */
    'notification-channels' => ['mail'],

    /**
     * Default notification strategy when user doesn't define one
     * Options: Padmission\Tickets\Enums\NotificationStrategy::Immediate, Padmission\Tickets\Enums\NotificationStrategy::Debounced
     *
     * @var string
     */
    'default-notification-strategy' => NotificationStrategy::Debounced,

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
