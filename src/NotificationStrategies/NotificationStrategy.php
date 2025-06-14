<?php

namespace Padmission\Tickets\NotificationStrategies;

use Padmission\Tickets\Models\Contracts\TicketInterface;

interface NotificationStrategy
{
    /**
     * Send notifications for a ticket
     *
     * @param TicketInterface $ticket
     * @return void
     */
    public function notify(TicketInterface $ticket): void;
}
