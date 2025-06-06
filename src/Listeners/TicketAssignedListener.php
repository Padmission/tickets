<?php

namespace Padmission\Tickets\Listeners;

use Padmission\Tickets\Events\TicketAssigned;

class TicketAssignedListener
{
    public function handle(TicketAssigned $event): void
    {
        // Handle the ticket assignment (e.g., notify user, log, etc.)
    }
}
