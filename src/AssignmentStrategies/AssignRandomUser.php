<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Padmission\Tickets\Models\Ticket;

final class AssignRandomUser extends PanelAwareAssignmentStrategy
{
    public function assign(Ticket $ticket): void
    {
        $users = $this->getEligibleUsersQuery($ticket)->get();

        if ($users->isEmpty()) {
            return;
        }

        $user = $users->random();
        $ticket->assignee_id = $user->getKey();
    }
}
