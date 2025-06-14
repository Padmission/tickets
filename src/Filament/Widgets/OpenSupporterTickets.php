<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Widgets\Traits\CanCalculatePollingInterval;
use Padmission\Tickets\Services\TicketMetricsService;

class OpenSupporterTickets extends BaseWidget
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
        $metricsService = resolve(TicketMetricsService::class);
        $metricsService->setCacheTime($this->getMaxPollingIntervalInSeconds());
        $count = $metricsService->getOpenTicketsWaitingOnSupportCount();

        return [
            Stat::make(__('padmission-tickets::widgets.open_support_tickets.label'), $count)
                ->description(__('padmission-tickets::widgets.open_tickets.description'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning'),
        ];
    }
}
