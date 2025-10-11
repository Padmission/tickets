<?php

namespace Padmission\Tickets\Policies;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Models\Ticket;

class TicketPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return true;
        }

        $panel = Filament::getPanel($ticket->panel);

        return $user->canAccessPanel($panel);
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return false;
        }

        $panel = Filament::getPanel($ticket->panel);

        return $user->canAccessPanel($panel);
    }

    public function manage(Authenticatable $user, Ticket $ticket): bool
    {
        return true;
    }

    public function escalate(Authenticatable $user, Ticket $ticket): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return true;
        }

        $panel = Filament::getPanel($ticket->panel);

        return $user->canAccessPanel($panel);
    }
}
