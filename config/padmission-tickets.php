<?php

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events;
use Padmission\Tickets\Http\Middleware\AuthenticateChatSession;
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

    'api' => [
        /**
         * The longest message, in characters, the chat API takes. Messages are
         * stored in a TEXT column of 65,535 bytes, which 16,000 characters fill
         * even at four bytes each.
         *
         * @var int
         */
        'max_message_length' => 16000,

        /**
         * How many times a minute one person (or, signed out, one address) may
         * write through the chat API: send, start a ticket, mark seen or ask
         * for an upload or download link. Reading is not limited.
         *
         * @var int
         */
        'writes_per_minute' => 60,

        /**
         * Middleware added to every chat API route after the package's own. The
         * routes run outside any panel, so neither a panel's middleware nor any
         * a host adds to its pages runs on them. AuthenticateChatSession is
         * Laravel's AuthenticateSession answering 401 rather than redirecting:
         * it ends a session a password change logged out. A host adds its own
         * checks, such as one that a user is still active.
         *
         * @var list<class-string|string>
         */
        'middleware' => [
            AuthenticateChatSession::class,
        ],
    ],

    'staff_api' => [
        /**
         * A token API for a panel's support staff, such as a desktop client,
         * working tickets as they would in that panel. Off until a host turns
         * it on and names the middleware that authenticates its tokens.
         *
         * @var bool
         */
        'enabled' => false,

        /**
         * The panel whose tickets, rules and supporters the API works as. Every
         * request runs as if inside it, so the panel's ticket query, scopes and
         * policies apply exactly as on its pages.
         *
         * @var string
         */
        'panel' => 'admin',

        /**
         * @var string
         */
        'prefix' => 'api/support/v1',

        /**
         * Middleware before the package's own, which must authenticate the
         * request, such as ['api', 'auth:sanctum']. Sign-in is the host's, since
         * it owns its users, passwords and second factors.
         *
         * @var list<class-string|string>
         */
        'middleware' => [],
    ],

    'scenarios' => [
        /**
         * The environments demo scenarios may be seeded in. They write made-up
         * conversations under real people's names, so anywhere else, production
         * above all, they refuse to run.
         *
         * @var list<string>
         */
        'environments' => ['local', 'testing', 'test', 'staging', 'preview', 'qa'],
    ],
];
