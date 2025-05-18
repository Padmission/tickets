<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Padmission\Tickets\Models\Ticket;

interface AssignmentStrategy
{
    public function assign(Ticket $ticket): void;
}
