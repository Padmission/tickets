<?php

namespace Padmission\Tickets\Filament\Widgets;

use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Widgets\ChartWidget;
use Padmission\Tickets\Services\TicketMetricsService;

class TicketBurndownChartWidget extends ChartWidget
{
    protected static ?string $pollingInterval = '60s';

    protected static ?string $maxHeight = '12.5rem';

    protected int|string|array $columnSpan = 'full';

    public int $days = 14;

    public function getHeading(): string
    {
        return __('padmission-tickets::widgets.burndown.heading');
    }

    protected function formatChartDate(Carbon $date): string
    {
        return $date->translatedFormat('M j');
    }

    public static function getColors(): array
    {
        $colors = Filament::getCurrentPanel()->getColors();

        return [
            'rgb('.FilamentColor::processColor($colors['primary'] ?? Color::Blue)[600].')',
            'rgb('.FilamentColor::processColor($colors['secondary'] ?? Color::Gray)[600].')',
        ];
    }

    protected function getData(): array
    {
        $raw = resolve(TicketMetricsService::class)
            ->setCacheTime($this->getPollingInterval())
            ->getBurndownData($this->days);

        [$colorA, $colorB] = static::getColors();

        $labels = [];
        $openCounts = [];
        $closedCounts = [];

        $cumulativeOpened = 0;
        $cumulativeClosed = 0;

        $openAtStart = $raw['openAtStart'];
        $opened = $raw['opened'];
        $closed = $raw['closed'];

        $startDate = $raw['startDate']->copy();
        $days = $startDate->diffInDays($raw['endDate']) + 1;

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateString = $date->toDateString();
            $labels[] = $this->formatChartDate($date);

            $openedToday = $opened[$dateString] ?? 0;
            $closedToday = $closed[$dateString] ?? 0;

            $cumulativeOpened += $openedToday;
            $cumulativeClosed += $closedToday;

            $openCounts[] = $openAtStart + $cumulativeOpened - $cumulativeClosed;
            $closedCounts[] = $closedToday;
        }

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
