<?php

namespace Padmission\Tickets\Models\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Models\Ticket;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Ticket $ticket): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, Ticket $ticket): bool
    {
        return true;
    }

    public function restore(Authenticatable $user, Ticket $ticket): bool
    {
        return true;
    }

    public function forceDelete(Authenticatable $user, Ticket $ticket): bool
    {
        return true;
    }
}
