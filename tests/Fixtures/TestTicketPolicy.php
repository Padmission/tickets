<?php

namespace Padmission\Tickets\Tests\Fixtures;

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

class TestTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function manage(User $user, Ticket $ticket): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function escalate(User $user, Ticket $ticket): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return true;
    }
}
