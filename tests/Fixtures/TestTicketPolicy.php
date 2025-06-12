<?php

namespace Padmission\Tickets\Tests\Fixtures;

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

class TestTicketPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
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
