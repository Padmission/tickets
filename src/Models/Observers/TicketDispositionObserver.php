<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Models\TicketDisposition;

class TicketDispositionObserver
{
    public function creating(TicketDisposition $disposition): void
    {
        $disposition->panel ??= Filament::getCurrentOrDefaultPanel()?->getId();
    }
}
