<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketMetricsService;

beforeEach(function () {
    Cache::flush();

    // Create necessary TicketStatus records for the factory methods to work
    TicketStatus::factory()->create([
        'display_name' => 'Open',
        'order' => 1,
        'panel' => 'test',
    ]);

    TicketStatus::factory()->create([
        'display_name' => 'Closed',
        'order' => 2,
        'panel' => 'test',
    ]);
});

describe('getOpenTicketsCount()', function () {
    test('it counts all tickets', function () {
        $service = new TicketMetricsService;

        Ticket::factory()->open()->create(['turn' => Turn::User]);
        Ticket::factory()->open()->create(['turn' => Turn::Supporter]);

        Ticket::factory()->closed()->create(['turn' => Turn::Supporter]);
        Ticket::factory()->closed()->create(['turn' => Turn::User]);

        expect($service->getOpenTicketsCount())->toBe(2);
    });
});

describe('getOpenTicketsWaitingOnSupportCount()', function () {
    it('it counts only open supporter tickets', function () {
        $service = new TicketMetricsService;

        Ticket::factory()->open()->create(['turn' => Turn::User]);
        Ticket::factory()->open()->create(['turn' => Turn::Supporter]);

        Ticket::factory()->closed()->create(['turn' => Turn::Supporter]);
        Ticket::factory()->closed()->create(['turn' => Turn::User]);

        expect($service->getOpenTicketsWaitingOnSupportCount())->toBe(1);
    });
});

describe('getAverageCloseTime()', function () {
    it('returns correct average', function () {
        $start = CarbonImmutable::today()->subDays();

        Ticket::factory()
            ->closed()
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
            ->closed()
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

        Ticket::factory()->open()->create(['created_at' => $start->subDay()]);
        Ticket::factory()->open()->create(['created_at' => $start]);

        $service = new TicketMetricsService;
        $data = $service->getBurndownData(2);

        expect($data)->openAtStart->toBe(1);
    });

    it('does not count tickets closed before as openedAtStart', function () {
        $start = CarbonImmutable::today()->subDay();

        Ticket::factory()->closed()->create([
            'created_at' => $start->subDay(),
            'closed_at' => $start->subDay(),
        ]);

        Ticket::factory()->closed()->create([
            'created_at' => $start->subDay(),
            'closed_at' => $start->addDays(2),
        ]);

        Ticket::factory()->open()->create(['created_at' => $start]);

        $service = new TicketMetricsService;
        $data = $service->getBurndownData(2);

        expect($data)->openAtStart->toBe(1);
    });

    it('does count opened tickets', function () {
        $start = CarbonImmutable::today()->subDay();

        Ticket::factory()->open()->create(['created_at' => $start->subDay()]);
        Ticket::factory()->open()->create(['created_at' => $start]);
        Ticket::factory()->open()->create(['created_at' => $start->addDay()]);
        Ticket::factory()->open()->create(['created_at' => $start->addDay()]);
        Ticket::factory()->open()->create(['created_at' => $start->addDays(2)]);

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

        Ticket::factory()->closed()->create([
            'created_at' => $start,
            'closed_at' => $start->subDay()->endOfDay(),
        ]);
        Ticket::factory()->closed()->create([
            'created_at' => $start,
            'closed_at' => $start->endOfDay(),
        ]);
        Ticket::factory()->closed()->create([
            'created_at' => $start,
            'closed_at' => $start->endOfDay(),
        ]);
        Ticket::factory()->closed()->create([
            'created_at' => $start,
            'closed_at' => $start->addDay()->endOfDay(),
        ]);
        Ticket::factory()->closed()->create([
            'created_at' => $start,
            'closed_at' => $start->addDays(2)->endOfDay(),
        ]);

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

        Ticket::factory()->closed()->create([
            'created_at' => $start->subDay(),
            'closed_at' => $start,
        ]);
        Ticket::factory()->closed()->create([
            'created_at' => $start,
            'closed_at' => $start->addDay(),
        ]);
        Ticket::factory()->closed()->create([
            'created_at' => $start,
            'closed_at' => $start->addDay(),
        ]);
        Ticket::factory()->open()->create(['created_at' => $start->addDay()]);

        $service = new TicketMetricsService;
        $data = $service->getBurndownChartData(2);

        expect($data)
            ->closedCounts->toHaveCount(2)
            ->closedCounts->toEqual([1, 2])
            ->openCounts->toHaveCount(2)
            ->openCounts->toEqual([2, 1]);
    });
});
