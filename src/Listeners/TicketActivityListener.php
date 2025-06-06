<?php

namespace Padmission\Tickets\Listeners;

use Padmission\Tickets\Events\TicketActivity;

class TicketActivityListener
{
    public function handle(TicketActivity $event): void
    {
        // Handle the ticket activity (e.g., log, notify, etc.)
    }
}
