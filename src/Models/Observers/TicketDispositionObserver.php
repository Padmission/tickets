<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Models\Contracts\TicketDispositionInterface;

class TicketDispositionObserver
{
    public function creating(TicketDispositionInterface $disposition): void
    {
        $disposition->panel ??= Filament::getCurrentPanel()->getId();
    }
}
