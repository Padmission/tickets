<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Filament\Widgets\Traits\CanCalculatePollingInterval;
use Padmission\Tickets\Services\TicketMetricsService;

/**
 * @property \Filament\Forms\Form $form
 */
class TicketMetricsWidget extends BaseWidget implements HasForms
{
    use CanCalculatePollingInterval;
    use InteractsWithForms;

    public ?int $timeRange = 7;

    protected static string $view = 'padmission-tickets::filament.widgets.stats-overview-widget';

    public function mount(): void
    {
        $this->form->fill([
            'timeRange' => $this->getDefaultTimeRange(),
        ]);
    }

    public function getDefaultTimeRange(): int
    {
        return 7;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('timeRange')
                    ->label(__('Time Range'))
                    ->options($this->getDateRangeOptions())
                    ->hiddenLabel()
                    ->live(debounce: 500)
                    ->nullable(false),
            ]);
    }

    protected function getHeading(): string
    {
        return __('Ticket Performance Metrics');
    }

    protected function getDescription(): ?string
    {
        return __('Statistics about ticket resolution times');
    }

    public function getDateRangeOptions(): array
    {
        return [
            1 => __('Last 1 day'),
            7 => __('Last 7 days'),
            30 => __('Last 30 days'),
            90 => __('Last 90 days'),
            365 => __('Last 365 days'),
            0 => __('All Time'),
        ];
    }

    protected function getStats(): array
    {
        $timeRangePeriod = $this->timeRange === 0 ? null : $this->timeRange;

        $metricsService = app(TicketMetricsService::class);
        $metricsService->setCacheTime($this->getPollingIntervalInSeconds());
        $metrics = $metricsService->getAverageCloseTime($timeRangePeriod);
        $detailedMetrics = $metricsService->getCloseTimeMetrics($timeRangePeriod);

        return [
            Stat::make(__('Average Close Time'), $metrics['average_close_time'])
                ->description(__(':count tickets closed', ['count' => $metrics['total_closed_tickets']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
            Stat::make(__('Fastest Resolution'), $detailedMetrics['minimum'])
                ->description(__('Best case scenario'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('success'),
            Stat::make(__('Slowest Resolution'), $detailedMetrics['maximum'])
                ->description(__('Worst case scenario'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('danger'),
        ];
    }
}
