<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;

class TicketAuth
{
    public function getUserId(): int|string|null
    {
        return Filament::auth()->id ?? session()->get('padmission-tickets::user_key');
    }
}
