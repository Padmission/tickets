<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Models\TicketActivity;

class TicketActivityObserver
{
    public function creating(TicketActivity $activity): void
    {
        $activity->user_id ??= auth()->user()?->id;
    }

    public function saved(TicketActivity $activity): void
    {
        event(new TicketActivityEvent($activity->ticket, $activity->type, null));
    }
}
