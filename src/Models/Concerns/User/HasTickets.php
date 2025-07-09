<?php

namespace Padmission\Tickets\Models\Concerns\User;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

trait HasTickets
{
    public function assignedTickets(): HasMany
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        return $this->hasMany($ticketModel, 'assignee_id');
    }

    public function submittedTickets(): HasMany
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        return $this->hasMany($ticketModel, 'submitter_id');
    }
}
