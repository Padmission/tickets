<?php

use Carbon\CarbonImmutable;
use Padmission\Tickets\Support\BusinessDayCutoff;

it('counts only local weekday hours', function (string $now, int $days, string $timezone, string $expected) {
    expect(BusinessDayCutoff::before(CarbonImmutable::parse($now, $timezone), $days, $timezone)->format('Y-m-d H:i:s'))
        ->toBe($expected);
})->with([
    'weekday' => ['2026-10-06 10:00:00', 1, 'UTC', '2026-10-05 10:00:00'],
    'Monday' => ['2026-10-05 10:00:00', 1, 'UTC', '2026-10-02 10:00:00'],
    'Saturday pauses' => ['2026-10-03 15:00:00', 1, 'UTC', '2026-10-02 00:00:00'],
    'Sunday pauses' => ['2026-10-04 15:00:00', 1, 'UTC', '2026-10-02 00:00:00'],
    'two days' => ['2026-10-05 10:00:00', 2, 'UTC', '2026-10-01 10:00:00'],
    'a full week' => ['2026-10-05 10:00:00', 5, 'UTC', '2026-09-28 10:00:00'],
    'spring DST' => ['2026-03-09 10:00:00', 1, 'America/New_York', '2026-03-06 10:00:00'],
    'autumn DST' => ['2026-11-02 10:00:00', 1, 'America/New_York', '2026-10-30 10:00:00'],
]);

it('rejects an invalid overdue threshold', function (int $days) {
    expect(fn () => BusinessDayCutoff::before(CarbonImmutable::now(), $days, 'UTC'))->toThrow(InvalidArgumentException::class);
})->with([0, -1]);
