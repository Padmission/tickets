<?php

namespace Padmission\Tickets\Services;

use Illuminate\Support\Facades\Cache;

class EmailStyleService
{
    /**
     * Get all email styles (core + custom)
     */
    public function getStyles(): string
    {
        return $this->getCoreMailStyles().$this->getTicketCustomStyles();
    }

    /**
     * Get Laravel's core mail styles with caching
     */
    protected function getCoreMailStyles(): string
    {
        return Cache::remember(__METHOD__, 3600, function () {
            $path = base_path(
                'vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/themes/default.css'
            );

            if (! file_exists($path)) {
                return '';
            }

            $contents = file_get_contents($path);

            return is_string($contents) ? $contents : '';
        });
    }

    /**
     * Get ticket-specific custom styles
     */
    protected function getTicketCustomStyles(): string
    {
        return '
        /* Modern base styles */
        .inner-body {
            margin-top: 0;
        }

        /* Button styles */
        a.button-blue,
        a.button-primary {
            color: #ffffff !important;
            background-color: #4f46e5 !important;
            border-radius: 8px !important;
            padding: 14px 32px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
        }

        /* Activity bubble styles */
        .activity-bubble {
            max-width: 85%;
        }

        .ticket-activity {
            margin-bottom: 16px;
            padding: 16px 20px;
            border-radius: 16px;
            background-color: #f1f5f9;
        }

        .ticket-activity.supporter {
            background-color: #4f46e5;
            color: #ffffff;
        }

        .ticket-activity.system {
            background-color: #fef3c7;
            color: #92400e;
            text-align: center;
            border-radius: 20px;
            padding: 8px 16px;
        }

        .activity-meta {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
        }

        .activity-content {
            line-height: 1.6;
            font-size: 15px;
            color: #334155;
        }

        .activity-content.supporter {
            color: #ffffff;
        }

        /* Link styles */
        a {
            color: #4f46e5;
        }

        /* Typography */
        p {
            margin: 0 0 16px 0;
            line-height: 1.625;
        }

        /* Card shadow for Outlook */
        .email-card {
            background-color: #ffffff;
            border-radius: 12px;
        }
    ';
    }
}
