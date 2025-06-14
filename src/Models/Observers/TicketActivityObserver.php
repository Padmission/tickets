<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Models\Contracts\TicketActivityInterface;

class TicketActivityObserver
{
    public function creating(TicketActivityInterface $activity): void
    {
        $activity->user_id ??= auth()->user()?->id;
    }

    public function saved(TicketActivityInterface $activity): void
    {
        event(new TicketActivityEvent($activity->ticket, $activity->type, null));
    }
}
