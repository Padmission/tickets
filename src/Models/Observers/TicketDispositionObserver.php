<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Contracts\TicketDispositionInterface;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketDispositionObserver
{
    public function creating(TicketDispositionInterface $disposition): void
    {
	    $disposition->panel ??= Filament::getCurrentPanel()->getId();
    }
}
