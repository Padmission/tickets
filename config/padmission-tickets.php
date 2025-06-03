<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;

return [
    /**
     * Swap package models (left) with your own model (right).
     * Your models should extend the package models to ensure type safety.
     *
     * @var array<class-string, class-string>
     */
    'models' => [
        Authenticatable::class => App\Models\User::class,
        Padmission\Tickets\Models\Ticket::class => Padmission\Tickets\Models\Ticket::class,
        Padmission\Tickets\Models\Activity::class => Padmission\Tickets\Models\Activity::class,
        Padmission\Tickets\Models\Priority::class => Padmission\Tickets\Models\Priority::class,
        Padmission\Tickets\Models\Status::class => Padmission\Tickets\Models\Status::class,
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
];
