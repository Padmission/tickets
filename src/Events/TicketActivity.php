<?php

namespace Padmission\Tickets\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Padmission\Tickets\Models\Ticket;

class TicketActivity
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Ticket $ticket;
    public string $activityType;
    public $metadata;

    public function __construct(Ticket $ticket, string $activityType, $metadata = null)
    {
        $this->ticket = $ticket;
        $this->activityType = $activityType;
        $this->metadata = $metadata;
    }
}
