<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\TicketPlugin;

class TicketActivityObserver
{
    public function creating(TicketActivity $activity): void
    {
        $activity->user_id ??= auth()->user()?->id;
    }

    public function created(TicketActivity $activity): void
    {
        /** @var Ticket|null $ticket */
        $ticket = TicketPlugin::resolveModelClass(Ticket::class)::query()
            ->withoutGlobalScopes()
            ->where('id', $activity->ticket_id)->first();

        event(new TicketActivityEvent($ticket, $activity->type, null, auth()->user()));
    }
}
