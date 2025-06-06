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
            Stat::make(__('Tickets Waiting on Support'), $count)
                ->description(__('Tickets with open status'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning'),
        ];
    }
}
