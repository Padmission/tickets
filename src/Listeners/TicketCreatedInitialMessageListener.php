<?php

namespace Padmission\Tickets\Listeners;

use Padmission\Tickets\Events\TicketActivity;
use Padmission\Tickets\Events\TicketAssigned;
use Padmission\Tickets\Events\TicketClosed;
use Padmission\Tickets\Events\TicketCreated;

class TicketCreatedInitialMessageListener {
    public function handle(TicketCreated $event): void
    {
        /**
         * TODO: Add in initial welcome message.
         */
    }
}
