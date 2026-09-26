<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Widgets\Concerns\DescribesTicketListTab;
use Padmission\Tickets\Services\TicketMetricsService;
use Padmission\Tickets\TicketPlugin;

class OpenSupporterTickets extends BaseWidget
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
        $query = $this->ticketsInActiveTab();

        $count = $query === null
            ? resolve(TicketMetricsService::class)
                ->setCacheTime($this->getPollingInterval())
                ->getOpenTicketsWaitingOnSupportCount()
            : $query->open()->where($query->qualifyColumn('turn'), Turn::Supporter)->count();

        // On an escalated ticket the support side is the team it went to, not
        // the team reading the list.
        $team = TicketPlugin::get()->getEscalationTargetName();
        $text = fn (string $line): string => $this->isOnEscalatedTab()
            ? TicketPlugin::teamText("padmission-tickets::widgets.escalations_waiting.{$line}", $team)
            : __("padmission-tickets::widgets.open_support_tickets.{$line}");

        return [
            Stat::make($text('label'), $count)
                ->description($text('description'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('warning'),
        ];
    }
}
