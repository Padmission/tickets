<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Events\TicketActivity as TicketActivityEvent;

class TicketActivityObserver
{


    public function saved(TicketActivity $activity): void
    {
        event(new TicketActivityEvent($activity->ticket, $activity->type, null));
    }
}
