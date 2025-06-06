<?php

namespace Padmission\Tickets\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Padmission\Tickets\Models\Ticket;

class TicketAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ticket $ticket;

    public $assignedTo;

    public function __construct(Ticket $ticket, $assignedTo)
    {
        $this->ticket = $ticket;
        $this->assignedTo = $assignedTo;
    }
}
