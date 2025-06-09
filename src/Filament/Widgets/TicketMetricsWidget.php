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

    /**
     * @var string
     */
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
                    ->label(__('padmission-tickets::tickets.widgets.time_range'))
                    ->options($this->getDateRangeOptions())
                    ->hiddenLabel()
                    ->live(debounce: 500)
                    ->nullable(false),
            ]);
    }

    protected function getHeading(): string
    {
        return __('padmission-tickets::tickets.widgets.ticket_performance_metrics');
    }

    protected function getDescription(): ?string
    {
        return __('padmission-tickets::tickets.widgets.statistics_about_ticket_resolution_times');
    }

    public function getDateRangeOptions(): array
    {
        return [
            1 => __('padmission-tickets::tickets.widgets.last_1_day'),
            7 => __('padmission-tickets::tickets.widgets.last_7_days'),
            30 => __('padmission-tickets::tickets.widgets.last_30_days'),
            90 => __('padmission-tickets::tickets.widgets.last_90_days'),
            365 => __('padmission-tickets::tickets.widgets.last_365_days'),
            0 => __('padmission-tickets::tickets.widgets.all_time'),
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
            Stat::make(__('padmission-tickets::tickets.widgets.average_close_time'), $metrics['average_close_time'])
                ->description(__('padmission-tickets::tickets.widgets.count_tickets_closed', ['count' => $metrics['total_closed_tickets']]))
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
            Stat::make(__('padmission-tickets::tickets.widgets.fastest_resolution'), $detailedMetrics['minimum'])
                ->description(__('padmission-tickets::tickets.widgets.best_case_scenario'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('success'),
            Stat::make(__('padmission-tickets::tickets.widgets.slowest_resolution'), $detailedMetrics['maximum'])
                ->description(__('padmission-tickets::tickets.widgets.worst_case_scenario'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('danger'),
        ];
    }
}
