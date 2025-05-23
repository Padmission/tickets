<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

trait HasAssignedTickets
{
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(
            TicketPlugin::resolveModelClass(Ticket::class),
            'assignee_id'
        );
    }
}
