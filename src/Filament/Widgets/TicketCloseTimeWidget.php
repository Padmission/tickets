<?php

namespace Padmission\Tickets\Filament\Widgets;

use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Services\TicketMetricsService;

class TicketCloseTimeWidget extends BaseWidget
{
    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 4;

    protected function getColumns(): int
    {
        return 1;
    }

    public function getStats(): array
    {
        $metrics = resolve(TicketMetricsService::class)
            ->setCacheTime($this->getPollingInterval())
            ->getAverageCloseTime(0);

        $averageFormatted = now()
            ->subSeconds($metrics['averageSeconds'])
            ->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE);

        return [
            Stat::make(__('padmission-tickets::widgets.close_time.label'), $averageFormatted)
                ->description(__('padmission-tickets::widgets.close_time.description', ['count' => $metrics['totalClosed']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
        ];
    }
}
