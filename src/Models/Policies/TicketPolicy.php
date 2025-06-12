<?php

namespace Padmission\Tickets\Models\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Models\Ticket;

class TicketPolicy
{
    use HandlesAuthorization;

    const string CREATE = 'create';

    /**
     * User can see tickets, scoped via `TicketPlugin::scopeTicketsForUser()`.
     */
    const string VIEW_ANY = 'viewAny';

    /**
     * User can respond as a supporter, edit the ticket and close it.
     */
    const string MANAGE = 'manage';

    const string ESCALATE = 'escalate';

    const string DELETE = 'delete';

    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
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
        return $this->manage($user, $ticket);
    }
}
