<?php

namespace Padmission\Tickets\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
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

        if ($query === null) {
            return [
                Stat::make(__('padmission-tickets::widgets.open_support_tickets.label'), resolve(TicketMetricsService::class)
                    ->setCacheTime($this->getPollingInterval())
                    ->getOpenTicketsWaitingOnSupportCount())
                    ->description(__('padmission-tickets::widgets.open_support_tickets.description'))
                    ->color('warning'),
            ];
        }

        // On an escalated ticket the support side is the team it went to, not
        // the team reading the list.
        $team = TicketPlugin::get()->getEscalationTargetName();

        if ($this->isOnEscalatedTab()) {
            return [
                Stat::make(
                    TicketPlugin::teamText('padmission-tickets::widgets.escalations_waiting.label', $team),
                    $query->open()->where($query->qualifyColumn('turn'), Turn::Supporter)->count(),
                )
                    ->description(TicketPlugin::teamText('padmission-tickets::widgets.escalations_waiting.description', $team))
                    ->color('gray'),
            ];
        }

        $needsYou = TicketResource::countNeedsYou($query);

        return [
            Stat::make(__('padmission-tickets::widgets.needs_you.label'), $needsYou)
                ->description(__('padmission-tickets::widgets.needs_you.description'))
                ->color($needsYou > 0 ? 'warning' : 'gray'),
        ];
    }
}
