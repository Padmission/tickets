<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;

class TicketActivityObserver
{
    public function creating(TicketActivity $activity): void
    {
        $activity->user_id ??= auth()->user()?->id;
    }

    public function created(TicketActivity $activity): void
    {
        /** @var Ticket|null $ticket */
        $ticket = $activity->ticket;
        event(new TicketActivityEvent($ticket, $activity->type, null, auth()->user()));
    }
}
