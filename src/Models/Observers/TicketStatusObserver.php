<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Models\Contracts\TicketStatusInterface;

class TicketStatusObserver
{
    public function creating(TicketStatusInterface $status): void
    {
        $status->panel ??= Filament::getCurrentPanel()?->getId();
    }
}
