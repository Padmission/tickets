<?php

namespace Padmission\Tickets\Filament\Widgets;

use Padmission\Tickets\Services\TicketMetricsService;
use Filament\Forms;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketMetricsWidget extends BaseWidget
{
    protected static string $view = 'padmission-tickets::filament.widgets.stats-overview-widget';

    public ?int $timeRange = 7;

    protected function getHeading(): string
    {
        return __('Ticket Performance Metrics');
    }

    protected function getDescription(): ?string
    {
        return __('Statistics about ticket resolution times');
    }

    public function getDateRangeOptions() : array {
        return [
            1 => __('Last 1 day'),
            7 => __('Last 7 days'),
            30 => __('Last 30 days'),
            90 => __('Last 90 days'),
            365 => __('Last 365 days'),
            0 => __('All Time'),
        ];
    }

    protected function getStats(): array
    {
        $timeRangePeriod = $this->timeRange === 0 ? null : $this->timeRange;
        $metricsService = app(TicketMetricsService::class);
        $metrics = $metricsService->getAverageCloseTime($timeRangePeriod);
        $detailedMetrics = $metricsService->getCloseTimeMetrics($timeRangePeriod);

        return [
            Stat::make(__('Average Close Time'), $metrics['average_close_time'])
                ->description(__(':count tickets closed', ['count' => $metrics['total_closed_tickets']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
            Stat::make(__('Fastest Resolution'), $detailedMetrics['minimum'])
                ->description(__('Best case scenario'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('success'),
            Stat::make(__('Slowest Resolution'), $detailedMetrics['maximum'])
                ->description(__('Worst case scenario'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('danger'),
        ];
    }
}
