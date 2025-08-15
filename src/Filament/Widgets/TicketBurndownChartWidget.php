<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Widgets\ChartWidget;
use Padmission\Tickets\Services\TicketMetricsService;

class TicketBurndownChartWidget extends ChartWidget
{
    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '12.5rem';

    protected int|string|array $columnSpan = 'full';

    public int $days = 14;

    public function getHeading(): string
    {
        return __('padmission-tickets::widgets.burndown.heading');
    }

    public static function getColors(): array
    {
        $colors = Filament::getCurrentOrDefaultPanel()->getColors();

        return [
            'rgb('.FilamentColor::processColor($colors['primary'] ?? Color::Blue)[600].')',
            'rgb('.FilamentColor::processColor($colors['secondary'] ?? Color::Gray)[600].')',
        ];
    }

    protected function getData(): array
    {
        [
            'labels' => $labels,
            'openCounts' => $openCounts,
            'closedCounts' => $closedCounts,
        ] = resolve(TicketMetricsService::class)
            ->setCacheTime($this->getPollingInterval())
            ->getBurndownChartData($this->days);

        [$colorA, $colorB] = static::getColors();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('padmission-tickets::widgets.burndown.open_at_end_of_day'),
                    'data' => $openCounts,
                    'borderColor' => $colorA,
                    'backgroundColor' => $colorA,
                    'pointBackgroundColor' => $colorA,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'fill' => false,
                ],
                [
                    'label' => __('padmission-tickets::widgets.burndown.closed_that_day'),
                    'data' => $closedCounts,
                    'borderColor' => $colorB,
                    'backgroundColor' => $colorB,
                    'pointBackgroundColor' => $colorB,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
