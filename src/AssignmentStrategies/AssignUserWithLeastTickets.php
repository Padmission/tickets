<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

final class AssignUserWithLeastTickets implements AssignmentStrategy
{
    public function assign(Ticket $ticket): void
    {
        $userModel = TicketPlugin::resolveUserModelClass();

        // TODO: Check if user can access the ticket/panel

        $user = $userModel::query()
            ->withCount([
                'assignedTickets' => fn ($query) => $query->open(),
            ])
            ->orderBy('assigned_tickets_count', 'ASC')
            ->first();

        $ticket->assignee_id = $user->getKey();
    }
}
