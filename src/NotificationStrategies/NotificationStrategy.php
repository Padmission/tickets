<?php

namespace Padmission\Tickets\NotificationStrategies;

use Padmission\Tickets\Models\Ticket;

interface NotificationStrategy
{
    /**
     * Send notifications for a ticket
     */
    public function notify(Ticket $ticket): void;
}
