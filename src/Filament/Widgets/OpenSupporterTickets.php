<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Services\TicketMetricsService;

class OpenSupporterTickets extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 4;

    protected function getColumns(): int
    {
        return 1;
    }

    public function getStats(): array
    {
        $count = resolve(TicketMetricsService::class)
            ->setCacheTime($this->getPollingInterval())
            ->getOpenTicketsWaitingOnSupportCount();

        return [
            Stat::make(__('padmission-tickets::widgets.open_support_tickets.label'), $count)
                ->description(__('padmission-tickets::widgets.open_tickets.description'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning'),
        ];
    }
}
