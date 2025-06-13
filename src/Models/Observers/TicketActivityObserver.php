<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Events\TicketActivity as TicketActivityEvent;
use Padmission\Tickets\Models\TicketActivity;

class TicketActivityObserver
{
    public function saved(TicketActivity $activity): void
    {
        event(new TicketActivityEvent($activity->ticket, $activity->type, null));
    }
}
