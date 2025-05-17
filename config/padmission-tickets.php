<?php

use Illuminate\Support\Facades\Config;

return [
    /**
     * Model class mappings
     *
     * Maps ticket system components to their respective model classes.
     * These mappings are used throughout the application to resolve
     * the correct model classes dynamically.
     *
     * @var array<string, class-string>
     */
    'models' => [
        'user' => App\Models\User::class,
        'ticket' => Padmission\Tickets\Models\Ticket::class,
        'activity' => Padmission\Tickets\Models\Activity::class,
        'priority' => Padmission\Tickets\Models\Priority::class,
        'status' => Padmission\Tickets\Models\Status::class,
    ],

    'levels' => [
        'default' => fn () => __('padmission-tickets::tickets.levels.default'),
        'escalated' => fn () => __('padmission-tickets::tickets.levels.escalated'),
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
];
