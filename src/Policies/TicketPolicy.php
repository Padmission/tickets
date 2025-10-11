<?php

namespace Padmission\Tickets\Policies;

use Filament\Facades\Filament;
use Padmission\Tickets\Models\Ticket;

class TicketPolicy
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return true;
        }

        $panel = Filament::getPanel($ticket->panel);

        return $user->canAccessPanel($panel);
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return false;
        }

        $panel = Filament::getPanel($ticket->panel);

        return $user->canAccessPanel($panel);
    }

    public function manage($user, Ticket $ticket): bool
    {
        return true;
    }

    public function escalate($user, Ticket $ticket): bool
    {
        return true;
    }

    public function delete($user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return true;
        }

        $panel = Filament::getPanel($ticket->panel);

        return $user->canAccessPanel($panel);
    }
}
