<?php

namespace Padmission\Tickets\NotificationStrategies;

use Padmission\Tickets\Models\Contracts\TicketInterface;

interface NotificationStrategy
{
    /**
     * Send notifications for a ticket
     */
    public function notify(TicketInterface $ticket): void;
}
