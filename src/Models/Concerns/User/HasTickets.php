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

        $relation = $this->hasMany($ticketModel, 'assignee_id');

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'assignedTickets']);
        }

        return $relation;
    }

    public function submittedTickets(): HasMany
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $relation = $this->hasMany($ticketModel, 'submitter_id');

        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();
        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'submittedTickets']);
        }

        return $relation;
    }
}
