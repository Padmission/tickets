<?php

namespace Padmission\Tickets\Policies;

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Support\ConversationViewer;
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

    /*
     * Hand over and Take over move an open escalation between people on the
     * team that escalated it, so they are asked of that team's panel.
     */
    public function handOver($user, Ticket $ticket): bool
    {
        if ($ticket->isClosed || ! $ticket->isEscalation()) {
            return false;
        }

        if ($user->id === $ticket->submitter_id) {
            return true;
        }

        $sourcePanel = $ticket->escalationSourcePanel();
        $viewer = ConversationViewer::current();

        // The page's own pool, resolved once per request, rather than a query per list row.
        if ($sourcePanel === $viewer->panelId && (string) $viewer->userId === (string) $user->getAuthIdentifier()) {
            return $viewer->isSupporter;
        }

        $supportersQuery = $sourcePanel === null ? null : TicketPlugin::find($sourcePanel)?->getAllSupportersQuery();

        if ($supportersQuery === null) {
            return false;
        }

        return app()->call($supportersQuery, ['ticket' => $ticket])
            ->whereKey($user->getAuthIdentifier())
            ->exists();
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
