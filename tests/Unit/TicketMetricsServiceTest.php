<?php

use Padmission\Tickets\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketMetricsService;
use Padmission\Tickets\Enums\Turn;
use Carbon\Carbon;

uses(TestCase::class);

describe('TicketMetricsService', function () {
    beforeEach(function () {
        // Clear cache before each test
        Cache::flush();
    });

    it('returns 0 open tickets when none exist', function () {
        $service = new TicketMetricsService();
        expect($service->getOpenTicketsCount())->toBe(0);
    });

    it('counts open tickets correctly', function () {
        Ticket::factory()->create(['closed_at' => null, 'turn' => Turn::Supporter]);
        $service = new TicketMetricsService();
        expect($service->getOpenTicketsCount())->toBe(1);
    });

    it('counts open tickets waiting on support correctly', function () {
        Ticket::factory()->create(['closed_at' => null, 'turn' => Turn::Supporter]);
        $service = new TicketMetricsService();
        expect($service->getOpenTicketsWaitingOnSupportCount())->toBe(1);
    });

    it('does not count closed tickets as open', function () {
        Ticket::factory()->create(['closed_at' => now(), 'turn' => Turn::Supporter]);
        $service = new TicketMetricsService();
        expect($service->getOpenTicketsCount())->toBe(0);
    });

    it('counts tickets within a date range', function () {
        Ticket::factory()->create(['closed_at' => Carbon::now()->subDays(2), 'created_at' => Carbon::now()->subDays(2), 'turn' => Turn::Supporter]);
        Ticket::factory()->create(['closed_at' => Carbon::now()->subDays(10), 'created_at' => Carbon::now()->subDays(10), 'turn' => Turn::Supporter]);
        $service = new TicketMetricsService();
        expect($service->getAverageCloseTime(7)['total_closed_tickets'])->toBe(1);
    });
});
