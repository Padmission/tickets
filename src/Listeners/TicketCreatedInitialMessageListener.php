<?php

namespace Padmission\Tickets\Listeners;

use Padmission\Tickets\Events\TicketCreated;

class TicketCreatedInitialMessageListener
{
    public function handle(TicketCreated $event): void
    {
        /**
         * TODO: Add in initial welcome message.
         */
    }
}
