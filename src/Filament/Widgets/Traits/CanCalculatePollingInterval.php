<?php

namespace Padmission\Tickets\Filament\Widgets\Traits;

use Carbon\CarbonInterval;

/**
 * I know this is overkill, but I figured we should set our caching on the widgets
 * to be the same as the polling interval for efficiency.  It prevents people with
 * the same widgets open from hitting the db over and over for the same data.
 */
trait CanCalculatePollingInterval
{
    /**
     * Convert Filament polling interval to seconds or null for infinite polling.
     *
     * @return int|null Seconds as integer, or null for infinite/no polling
     */
    public function getPollingIntervalInSeconds(): ?int
    {
        return once(function() {

            $interval = $this->getPollingInterval();

            if (! $interval) {
                return null;
            }

            if ($interval === true) {
                return 2;
            }

            if (is_numeric($interval)) {
                return (int) $interval > 0 ? (int) $interval : null;
            }

            if (is_string($interval)) {
                $interval = strtolower(trim($interval));

                if (in_array($interval, ['infinite', 'never', 'none', 'off'])) {
                    return null;
                }

                try {
                    if (str_starts_with($interval, 'p')) {
                        $carbonInterval = CarbonInterval::fromString(strtoupper($interval));

                        return $carbonInterval->totalSeconds > 0 ? (int) ceil($carbonInterval->totalSeconds) : null;
                    }
                } catch (\Exception $e) {
                }

                if (preg_match('/^(\d+(?:\.\d+)?)\s*([a-z]+)$/', $interval, $matches)) {
                    $value = (float) $matches[1];
                    $unit = $matches[2];

                    return match ($unit) {
                        's', 'sec', 'second', 'seconds' => (int) ceil($value),
                        'm', 'min', 'minute', 'minutes' => (int) ceil($value * 60),
                        'h', 'hr', 'hour', 'hours' => (int) ceil($value * 3600),
                        'd', 'day', 'days' => (int) ceil($value * 86400),
                        'ms' => max(1, (int) ceil($value * 0.001)),
                        default => null,
                    };
                }

                if (is_numeric($interval)) {
                    return (int) $interval > 0 ? (int) $interval : null;
                }
            }

            if (is_array($interval)) {
                if (isset($interval['seconds'])) {
                    return (int) $interval['seconds'] > 0 ? (int) $interval['seconds'] : null;
                }
                if (isset($interval['minutes'])) {
                    return (int) $interval['minutes'] * 60;
                }
                if (isset($interval['hours'])) {
                    return (int) $interval['hours'] * 3600;
                }
            }

            return null;
        });

    }
}
