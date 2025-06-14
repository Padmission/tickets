<?php

namespace Padmission\Tickets\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Padmission\Tickets\Models\Ticket;

class TicketPriorityChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public int $oldPriorityId,
        public int $newPriorityId
    ) {}
}
