<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Widgets\Traits\CanCalculatePollingInterval;
use Padmission\Tickets\Services\TicketMetricsService;

class TicketTimeToCloseWidget extends BaseWidget
{
    use CanCalculatePollingInterval;

    protected static ?string $pollingInterval = '60s';

    public function getStats(): array
    {

        $timeRangePeriod = 0;

        $metricsService = app(TicketMetricsService::class);
        $metricsService->setCacheTime($this->getPollingIntervalInSeconds());
        $metrics = $metricsService->getAverageCloseTime($timeRangePeriod);

        return [
            Stat::make(__('padmission-tickets::tickets.widgets.average_close_time'), $metrics['average_close_time'])
                ->description(__('padmission-tickets::tickets.widgets.count_tickets_closed', ['count' => $metrics['total_closed_tickets']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
        ];
    }
}
