<?php

namespace Padmission\Tickets\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class BusinessDayCutoff
{
    public static function before(CarbonImmutable $now, int $days, string $timezone): CarbonImmutable
    {
        if ($days < 1) {
            throw new InvalidArgumentException('The overdue threshold must be at least one business day.');
        }

        // Work in local calendar hours so weekends and daylight-saving changes
        // do not move a Friday 10 a.m. deadline away from Monday 10 a.m.
        $local = $now->setTimezone($timezone);
        $day = CarbonImmutable::parse($local->format('Y-m-d'), 'UTC');
        $available = $local->hour * 3600 + $local->minute * 60 + $local->second;
        $remaining = $days * 86400;

        while (true) {
            if (! $day->isWeekend()) {
                if ($remaining <= $available) {
                    return CarbonImmutable::parse($day->addSeconds($available - $remaining)->format('Y-m-d H:i:s'), $timezone);
                }

                $remaining -= $available;
            }

            $day = $day->subDay();
            $available = 86400;
        }
    }
}
