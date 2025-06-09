<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Widgets\Traits\CanCalculatePollingInterval;
use Padmission\Tickets\Services\TicketMetricsService;

class OpenTicketsWidget extends BaseWidget
{
    use CanCalculatePollingInterval;

    protected static ?string $pollingInterval = '60s';

    public function getStats(): array
    {

        $metricsService = app(TicketMetricsService::class);
        $metricsService->setCacheTime($this->getPollingIntervalInSeconds());
        $count = $metricsService->getOpenTicketsCount();

        return [
            Stat::make(__('padmission-tickets::tickets.widgets.tickets_open'), $count)
                ->description(__('padmission-tickets::tickets.widgets.tickets_with_open_status'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning'),
        ];
    }
}
