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
        .inner-body { margin-top: 1.25rem; }
        a.button-blue, a.button-primary { color: #fff; }
        .ticket-activity { margin-bottom: 1rem; padding: 1rem; border-left: 4px solid #e5e7eb; }
        .activity-meta { font-size: 0.9em; color: #666; margin-bottom: 0.5rem; }
        .activity-content { line-height: 1.5; }
    ';
    }
}
