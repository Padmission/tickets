<?php

namespace App\Policies;

use App\Models\User;
use Padmission\Tickets\Models\Ticket;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return true;
    }

    public function escalate(User $user, Ticket $ticket): bool
    {
        return true;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return true;
    }
}
