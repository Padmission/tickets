<?php

namespace Padmission\Tickets\AssignmentStrategies;

use Closure;
use Padmission\Tickets\Models\Ticket;

final class AssignDefaultUser implements AssignmentStrategy
{
    public function __construct(
        public int|Closure $userId
    ) {}

    public function assign(Ticket $ticket): void
    {
        $ticket->assignee_id = value($this->userId);
    }
}
