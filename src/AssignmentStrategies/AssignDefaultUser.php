<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Closure;
use Filament\Facades\Filament;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

final class AssignDefaultUser implements AssignmentStrategy
{
    public function __construct(
        public int|Closure $userId
    ) {}

    public function assign(Ticket $ticket): void
    {
        $userId = value($this->userId);

        $this->ensureUserIsEligibleSupporter($userId, $ticket);

        $ticket->assignee_id = $userId;
    }

    private function ensureUserIsEligibleSupporter(int $userId, Ticket $ticket): void
    {
        $targetPanelId = $ticket->panel ?? Filament::getCurrentOrDefaultPanel()->getId();
        $targetPlugin = TicketPlugin::get($targetPanelId);
        $allSupportersQuery = $targetPlugin->getAllSupportersQuery();

        if ($allSupportersQuery) {
            $eligibleUserIds = app()->call($allSupportersQuery)->pluck('id')->toArray();

            if (! in_array($userId, $eligibleUserIds)) {
                throw new RuntimeException(
                    "User ID {$userId} is not in the allSupportersQuery for panel '{$targetPanelId}'. ".
                    'Only users defined in allSupportersQuery can be assigned tickets.'
                );
            }
        }
    }
}
