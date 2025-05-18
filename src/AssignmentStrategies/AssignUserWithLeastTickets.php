<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

final class AssignUserWithLeastTickets implements AssignmentStrategy
{
    public function assign(Ticket $ticket): void
    {
        $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);

        // TODO: Check if user can access the ticket/panel

        $user = $userModel::query()
            ->withCount('assignedTickets')
            ->orderBy('assigned_tickets_count', 'ASC')
            ->first();

        $ticket->assignee_id = $user->getKey();
    }
}
