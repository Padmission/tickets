<?php

namespace Padmission\Tickets\AssignmentStrategies;

use RuntimeException;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

abstract class PanelAwareAssignmentStrategy implements AssignmentStrategy
{
    protected function getEligibleUsersQuery(Ticket $ticket): Builder
    {
        $targetPanelId = $ticket->panel ?? Filament::getCurrentOrDefaultPanel()->getId();
        $targetPlugin = TicketPlugin::get($targetPanelId);

        $query = $this->tryGetInitialAssignmentQuery()
            ?? $this->tryGetAllSupportersQuery($targetPlugin);

        if (! $query) {
            $this->throwMissingSupportersQueryException($targetPanelId);
        }

        return $query;
    }

    private function tryGetInitialAssignmentQuery(): ?Builder
    {
        $currentPlugin = TicketPlugin::get();
        $initialAssignmentQuery = $currentPlugin->getInitialAssignmentSupportersQuery();

        return $initialAssignmentQuery ? app()->call($initialAssignmentQuery) : null;
    }

    private function tryGetAllSupportersQuery(TicketPlugin $targetPlugin): ?Builder
    {
        $allSupportersQuery = $targetPlugin->getAllSupportersQuery();

        return $allSupportersQuery ? app()->call($allSupportersQuery) : null;
    }

    private function throwMissingSupportersQueryException(string $targetPanelId): never
    {
        throw new RuntimeException("No supporters query configured for panel '{$targetPanelId}'");
    }
}
