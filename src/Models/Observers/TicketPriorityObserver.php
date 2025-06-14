<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Contracts\TicketInterface;
use Padmission\Tickets\Models\Contracts\TicketPriorityInterface;
use Padmission\Tickets\Models\Contracts\TicketStatusInterface;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketPriorityObserver
{
    public function creating(TicketPriorityInterface $priority): void
    {
        $priority->panel ??= Filament::getCurrentPanel()?->getId();
    }
}
