<?php

namespace Padmission\Tickets\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Padmission\Tickets\Models\Ticket;

class TicketHandedOverEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public ?Authenticatable $actor,
        public int|string|null $fromId,
        public int|string $toId,
    ) {}
}
