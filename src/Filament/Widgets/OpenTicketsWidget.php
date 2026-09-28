<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Widgets\Concerns\DescribesTicketListTab;
use Padmission\Tickets\Services\TicketMetricsService;

class OpenTicketsWidget extends BaseWidget
{
    use DescribesTicketListTab;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 4;

    protected function getColumns(): int
    {
        return 1;
    }

    public function getStats(): array
    {
        $count = $this->ticketsInActiveTab()?->open()->count()
            ?? resolve(TicketMetricsService::class)
                ->setCacheTime($this->getPollingInterval())
                ->getOpenTicketsCount();

        return [
            Stat::make(__('padmission-tickets::widgets.open_tickets.label'), $count)
                ->description($this->isOnEscalatedTab()
                    ? __('padmission-tickets::widgets.open_tickets.description_escalations')
                    : __('padmission-tickets::widgets.open_tickets.description'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('gray'),
        ];
    }
}
