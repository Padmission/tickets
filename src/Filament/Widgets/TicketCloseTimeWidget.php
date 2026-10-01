<?php

namespace Padmission\Tickets\Filament\Widgets;

use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Widgets\Concerns\DescribesTicketListTab;
use Padmission\Tickets\Services\TicketMetricsService;

class TicketCloseTimeWidget extends BaseWidget
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
        $service = resolve(TicketMetricsService::class)->setCacheTime($this->getPollingInterval());
        $query = $this->ticketsInActiveTab();
        $metrics = $query === null ? $service->getAverageCloseTime(0) : $service->averageCloseTimeFor($query);

        $averageFormatted = $metrics['totalClosed'] === 0
            ? '–'
            : now()->subSeconds($metrics['averageSeconds'])->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE);

        return [
            Stat::make(__('padmission-tickets::widgets.close_time.label'), $averageFormatted)
                ->description(trans_choice('padmission-tickets::widgets.close_time.description', $metrics['totalClosed'], ['count' => $metrics['totalClosed']]))
                ->color('primary'),
        ];
    }
}
