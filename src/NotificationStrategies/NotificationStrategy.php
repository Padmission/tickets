<?php

namespace Padmission\Tickets\NotificationStrategies;

use Padmission\Tickets\Models\Ticket;

interface NotificationStrategy
{
    public function notify(Ticket $ticket): void;
}
