<?php

namespace Padmission\Tickets\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;

class TicketActivityEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $activityType;

    public $metadata;

    public function __construct(public Ticket $ticket, ActivityType|string $activityType, $metadata = null)
    {
        $this->activityType = $activityType instanceof ActivityType ? $activityType->value : $activityType;
        $this->metadata = $metadata;
    }
}
