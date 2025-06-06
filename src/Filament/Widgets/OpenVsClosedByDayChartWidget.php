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

    protected function getData(): array
    {
        return Cache::remember(__METHOD__, $this->getPollingInterval(), function () {

            $service = app(TicketMetricsService::class);
            $data = $service->getOpenVsClosedByDayChartData($this->days);

            $colors = static::getCurrentSwatch();

            $colorA = $colors['primary'] ? $colors['primary'][500] : '#3b82f6';
            $colorB = $colors['secondary'] ? $colors['secondary'][500] : '#10b981';

            $colorA = strpos($colorA, '#') === 0 ? $colorA : 'rgb('.$colorA.')';
            $colorB = strpos($colorB, '#') === 0 ? $colorB : 'rgb('.$colorB.')';

            return [
                'labels' => $data['labels'],
                'datasets' => [
                    [
                        'label' => __('Opened that day'),
                        'data' => array_key_exists(0, $data['datasets']) ? $data['datasets'][0]['data'] : [],
                        'borderColor' => $colorA,
                        'backgroundColor' => $colorA,
                        'pointBackgroundColor' => $colorA,
                        'tension' => 0.4,
                        'pointRadius' => 3,
                        'fill' => false,
                    ],
                    [
                        'label' => __('Closed that day'),
                        'data' => array_key_exists(1, $data['datasets']) ? $data['datasets'][1]['data'] : [],
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
