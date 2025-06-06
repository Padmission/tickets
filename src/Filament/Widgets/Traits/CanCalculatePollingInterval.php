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
        return once(function () {
            $interval = $this->getPollingInterval();

            switch (gettype($interval)) {
                case 'NULL':
                    return null;
                case 'boolean':
                    return $interval ? 2 : null;
                case 'integer':
                    return $interval > 0 ? $interval : null;
                case 'double':
                    return $interval > 0 ? (int) $interval : null;
                case 'string':
                    $str = strtolower(trim($interval));
                    if (in_array($str, ['infinite', 'never', 'none', 'off'], true)) {
                        return null;
                    }
                    try {
                        if (str_starts_with($str, 'p')) {
                            $carbonInterval = \Carbon\CarbonInterval::fromString(strtoupper($str));
                            return $carbonInterval->totalSeconds > 0 ? (int) ceil($carbonInterval->totalSeconds) : null;
                        }
                    } catch (\Exception $e) {}
                    if (preg_match('/^(\d+(?:\.\d+)?)\s*([a-z]+)$/', $str, $matches)) {
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
                    // If it's a numeric string
                    if (is_numeric($str)) {
                        $intval = (int) $str;
                        return $intval > 0 ? $intval : null;
                    }
                    return null;
                default:
                    return null;
            }
        });
    }
}
