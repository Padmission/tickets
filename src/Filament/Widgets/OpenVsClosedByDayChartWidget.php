<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Support\Colors\Color;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Padmission\Tickets\Filament\Widgets\Traits\CanCalculatePollingInterval;
use Padmission\Tickets\Services\TicketMetricsService;

class OpenVsClosedByDayChartWidget extends ChartWidget
{
    use CanCalculatePollingInterval;

    protected static ?string $pollingInterval = '60s';

    protected static ?string $maxHeight = '300px';

    public int $days = 14;

    public function getHeading(): string
    {
        return __('Open vs Closed Tickets by Day');
    }

    public static function getCurrentSwatch(): array
    {
        $defaultColors = [
            'primary' => Color::Blue,
            'secondary' => Color::Gray,
        ];

        $currentColors = Color::all();

        $swatch = [];

        foreach ($defaultColors as $name => $defaultColor) {
            $swatch[$name] = self::getColorShades($currentColors[$name] ?? $defaultColor);
        }

        return $swatch;
    }

    private static function getColorShades($color): array
    {
        return [
            50 => $color[50],
            100 => $color[100],
            200 => $color[200],
            300 => $color[300],
            400 => $color[400],
            500 => $color[500],
            600 => $color[600],
            700 => $color[700],
            800 => $color[800],
            900 => $color[900],
            950 => $color[950],
        ];
    }

    protected function formatChartDate(\Carbon\Carbon $date): string
    {
        return $date->translatedFormat('M j');
    }

    protected function getData(): array
    {
        return Cache::remember(__METHOD__, $this->getPollingInterval(), function () {

            $service = app(TicketMetricsService::class);
            $raw = $service->getOpenVsClosedByDayChartData($this->days);

            $colors = static::getCurrentSwatch();
            $colorA = $colors['primary'] ? $colors['primary'][500] : '#3b82f6';
            $colorB = $colors['secondary'] ? $colors['secondary'][500] : '#10b981';
            $colorA = strpos($colorA, '#') === 0 ? $colorA : 'rgb(' . $colorA . ')';
            $colorB = strpos($colorB, '#') === 0 ? $colorB : 'rgb(' . $colorB . ')';

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
                $dateStr = $date->toDateString();
                $labels[] = $this->formatChartDate($date);

                $openedToday = $opened[$dateStr] ?? 0;
                $closedToday = $closed[$dateStr] ?? 0;

                $cumulativeOpened += $openedToday;
                $cumulativeClosed += $closedToday;

                $openCounts[] = $openAtStart + $cumulativeOpened - $cumulativeClosed;
                $closedCounts[] = $closedToday;
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Open at End of Day'),
                        'data' => $openCounts,
                        'borderColor' => $colorA,
                        'backgroundColor' => $colorA,
                        'pointBackgroundColor' => $colorA,
                        'tension' => 0.4,
                        'pointRadius' => 3,
                        'fill' => false,
                    ],
                    [
                        'label' => __('Closed that day'),
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
        });
    }

    protected function getType(): string
    {
        return 'line';
    }
}
