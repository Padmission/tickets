<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Widgets\Traits\CanCalculatePollingInterval;
use Padmission\Tickets\Services\TicketMetricsService;

class TicketClosingTimeWidget extends BaseWidget
{
    use CanCalculatePollingInterval;

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 4;

    protected function getColumns(): int
    {
        return 1;
    }

    public function getStats(): array
    {
        $metricsService = app(TicketMetricsService::class);
        $metricsService->setCacheTime($this->getMaxPollingIntervalInSeconds());
        $metrics = $metricsService->getAverageCloseTime(0);

        return [
            Stat::make(__('padmission-tickets::widgets.close_time.label'), $metrics['average_close_time'])
                ->description(__('padmission-tickets::widgets.close_time.description', ['count' => $metrics['total_closed_tickets']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
        ];
    }
}
