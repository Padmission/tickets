<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Padmission\Tickets\Models\Ticket;

final class AssignUserWithLeastTickets extends PanelAwareAssignmentStrategy
{
    public function assign(Ticket $ticket): void
    {
        $users = $this->getEligibleUsersQuery($ticket)
            ->withCount([
                'assignedTickets' => fn ($query) => $query->open(),
            ])
            ->orderBy('assigned_tickets_count', 'ASC')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $user = $this->getUserWithLowestCount($users);
        $ticket->assignee_id = $user->getKey();
    }

    private function getUserWithLowestCount($users)
    {
        $lowestCount = $users->first()->assigned_tickets_count;

        $usersWithLowestCount = $users->filter(
            fn ($user) => $user->assigned_tickets_count === $lowestCount
        );

        return $usersWithLowestCount->random();
    }
}
