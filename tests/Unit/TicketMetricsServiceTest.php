<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketMetricsService;

beforeEach(fn () => Cache::flush());

describe('getOpenTicketsCount()', function () {
    test('it counts all tickets', function () {
        $service = new TicketMetricsService;

        Ticket::factory()
            ->count(4)
            ->sequence(
                ['closed_at' => null, 'turn' => Turn::User],
                ['closed_at' => null, 'turn' => Turn::Supporter],
                ['closed_at' => now(), 'turn' => Turn::Supporter],
                ['closed_at' => now(), 'turn' => Turn::User]
            )
            ->create();

        expect($service->getOpenTicketsCount())->toBe(2);
    });
});

describe('getOpenTicketsWaitingOnSupportCount()', function () {
    it('it counts only open supporter tickets', function () {
        $service = new TicketMetricsService;

        Ticket::factory()
            ->count(4)
            ->sequence(
                ['closed_at' => null, 'turn' => Turn::User],
                ['closed_at' => null, 'turn' => Turn::Supporter],
                ['closed_at' => now(), 'turn' => Turn::Supporter],
                ['closed_at' => now(), 'turn' => Turn::User]
            )
            ->create();

        expect($service->getOpenTicketsWaitingOnSupportCount())->toBe(1);
    });
});

describe('getAverageCloseTime()', function () {
    it('returns correct average', function () {
        $start = CarbonImmutable::today()->subDays();

        Ticket::factory()
            ->sequence(
                ['created_at' => $start, 'closed_at' => $start->addMinutes(2)],
                ['created_at' => $start, 'closed_at' => $start->addMinutes(3)],
                ['created_at' => $start, 'closed_at' => $start->addMinutes(4)]
            )
            ->count(3)
            ->create();

        $service = new TicketMetricsService;
        $data = $service->getAverageCloseTime(7);

        expect($data)
            ->totalClosed->toBe(3)
            ->averageSeconds->toBe((2 + 3 + 4) / 3 * 60);
    });

    it('only counts tickets in range', function () {
        $start = CarbonImmutable::now()->subDays();

        Ticket::factory()
            ->sequence(
                ['created_at' => $start->subDays(7)->subMinute(), 'closed_at' => $start->subDays(5)],
                ['created_at' => $start, 'closed_at' => $start->addMinutes(3)],
                ['created_at' => $start, 'closed_at' => $start->addMinutes(4)]
            )
            ->count(3)
            ->create();

        $service = new TicketMetricsService;
        $data = $service->getAverageCloseTime(8);

        expect($data)
            ->totalClosed->toBe(2)
            ->averageSeconds->toEqual((3 + 4) / 2 * 60);
    });

});

describe('getBurndownData()', function () {
    it('counts only tickets before date as openedAtStart', function () {
        $start = CarbonImmutable::today()->subDay();

        Ticket::factory()
            ->sequence(
                ['created_at' => $start->subDay()],
                ['created_at' => $start],
            )
            ->count(2)
            ->create(['closed_at' => null]);

        $service = new TicketMetricsService;
        $data = $service->getBurndownData(2);

        expect($data)->openAtStart->toBe(1);
    });

    it('does not count tickets closed before as openedAtStart', function () {
        $start = CarbonImmutable::today()->subDay();

        Ticket::factory()
            ->sequence(
                // Closed before
                ['created_at' => $start->subDay(), 'closed_at' => $start->subDay()],
                // Still open
                ['created_at' => $start->subDay(), 'closed_at' => $start->addDays(2)],
                // Not before
                ['created_at' => $start],
            )
            ->count(3)
            ->create();

        $service = new TicketMetricsService;
        $data = $service->getBurndownData(2);

        expect($data)->openAtStart->toBe(1);
    });

    it('does count opened tickets', function () {
        $start = CarbonImmutable::today()->subDay();

        Ticket::factory()
            ->sequence(
                ['created_at' => $start->subDay()],
                ['created_at' => $start],
                ['created_at' => $start->addDay()],
                ['created_at' => $start->addDay()],
                ['created_at' => $start->addDays(2)],
            )
            ->count(5)
            ->create();

        $service = new TicketMetricsService;
        $data = $service->getBurndownData(2);

        expect($data)
            ->opened->toHaveCount(2)
            ->toEqual([
                $start->toDateString() => 1,
                $start->addDay()->toDateString() => 2,
            ]);
    });

    it('does count closed tickets', function () {
        $start = CarbonImmutable::today()->subDay();

        Ticket::factory()
            ->sequence(
                ['created_at' => $start, 'closed_at' => $start->subDay()->endOfDay()],
                ['created_at' => $start, 'closed_at' => $start->endOfDay()],
                ['created_at' => $start, 'closed_at' => $start->endOfDay()],
                ['created_at' => $start, 'closed_at' => $start->addDay()->endOfDay()],
                ['created_at' => $start, 'closed_at' => $start->addDays(2)->endOfDay()],
            )
            ->count(5)
            ->create();

        $service = new TicketMetricsService;
        $data = $service->getBurndownData(2);

        expect($data)
            ->closed->toHaveCount(2)
            ->toEqual([
                $start->toDateString() => 2,
                $start->addDay()->toDateString() => 1,
            ]);
    });
});

describe('getBurndownChartData()', function () {
    it('calculates data correctly', function () {

        $start = CarbonImmutable::today()->subDay();

        Ticket::factory()
            ->sequence(
                ['created_at' => $start->subDay(), 'closed_at' => $start],
                ['created_at' => $start, 'closed_at' => $start->addDay()],
                ['created_at' => $start, 'closed_at' => $start->addDay()],
                ['created_at' => $start->addDay(), 'closed_at' => null],
            )
            ->count(4)
            ->create();

        $service = new TicketMetricsService;
        $data = $service->getBurndownChartData(2);

        expect($data)
            ->closedCounts->toHaveCount(2)
            ->closedCounts->toEqual([1, 2])
            ->openCounts->toHaveCount(2)
            ->openCounts->toEqual([2, 1]);
    });
});
