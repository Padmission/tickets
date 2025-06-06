<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Services\TicketMetricsService;

class OpenTicketsWidget extends BaseWidget
{
    public function getStats(): array
    {
        $service = app(TicketMetricsService::class);
        $count = $service->getOpenTicketsCount();

        return [
            Stat::make(__('Tickets Waiting on Support'), $count)
                ->description(__('Tickets with open status'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning'),
        ];
    }
}
