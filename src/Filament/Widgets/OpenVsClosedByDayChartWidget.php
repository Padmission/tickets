<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Padmission\Tickets\Services\TicketMetricsService;

class OpenVsClosedByDayChartWidget extends ChartWidget
{
    protected static ?string $maxHeight = '300px';

    public int $days = 14;

    public function getHeading(): string
    {
        return __('Open vs Closed Tickets by Day');
    }

    protected function getData(): array
    {
        $service = app(TicketMetricsService::class);
        return $service->getOpenVsClosedByDayChartData($this->days);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
