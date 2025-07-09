<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Padmission\Tickets\Models\Ticket;

final class DoNotAssign implements AssignmentStrategy
{
    public function assign(Ticket $ticket): void
    {
        // Intentionally do nothing - leave ticket unassigned
    }
}
