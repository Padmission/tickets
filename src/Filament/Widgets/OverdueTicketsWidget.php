<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\Concerns\DescribesTicketListTab;

class OverdueTicketsWidget extends BaseWidget
{
    use DescribesTicketListTab;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 3;

    protected function getColumns(): int
    {
        return 1;
    }

    public function getStats(): array
    {
        $query = $this->ticketsInActiveTab() ?? TicketResource::allTicketsQuery();
        $count = $query->overdue()->count();
        $parameters = [
            'tab' => $this->activeTab ?? 'all',
            'filters' => [
                ...($this->tableFilters ?? []),
                'overdue' => ['isActive' => true],
            ],
            'search' => $this->tableSearch,
        ];

        return [
            Stat::make(__('padmission-tickets::widgets.overdue.label'), $count)
                ->description(__('padmission-tickets::widgets.overdue.description'))
                ->color($count > 0 ? 'danger' : 'gray')
                ->url(TicketResource::getUrl('index', $parameters)),
        ];
    }
}
