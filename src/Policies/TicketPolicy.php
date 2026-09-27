<?php

namespace Padmission\Tickets\Policies;

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketPolicy
{
    public function viewAny($user): bool
    {
        // The resource is visible to any authenticated panel entrant, but the row set is
        // scoped in TicketResource::getEloquentQuery(): non-supporters only ever see the
        // tickets they submitted, while genuine supporters see the whole panel query.
        return true;
    }

    public function view($user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return true;
        }

        return $this->isSupporter($user, $ticket);
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

        return $this->isSupporter($user, $ticket);
    }

    public function manage($user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->submitter_id) {
            return false;
        }

        return $this->isSupporter($user, $ticket);
    }

    public function reply($user, Ticket $ticket): bool
    {
        return $this->manage($user, $ticket);
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

        return $this->isSupporter($user, $ticket);
    }

    private function isSupporter($user, Ticket $ticket): bool
    {
        $supportersQuery = TicketPlugin::get($ticket->panel)->getAllSupportersQuery();

        if ($supportersQuery === null) {
            return false;
        }

        return app()->call($supportersQuery)
            ->whereKey($user->getAuthIdentifier())
            ->exists();
    }
}
