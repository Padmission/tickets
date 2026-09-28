<?php

namespace Padmission\Tickets\Policies;

use Filament\Facades\Filament;
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
        if ($ticket->isSubmittedBy($user)) {
            return true;
        }

        return $this->isSupporter($user, $ticket);
    }

    public function create($user): bool
    {
        return true;
    }

    /*
     * Supporters start tickets from the ticket list, for someone in their
     * organization or as a question for the team they escalate to. Everyone
     * else starts one from the chat.
     */
    public function openTicketFromList($user): bool
    {
        return TicketPlugin::find(Filament::getCurrentPanel()?->getId())?->isSupporter($user) ?? false;
    }

    public function update($user, Ticket $ticket): bool
    {
        if ($ticket->isSubmittedBy($user)) {
            return false;
        }

        return $this->isSupporter($user, $ticket);
    }

    public function manage($user, Ticket $ticket): bool
    {
        if ($ticket->isSubmittedBy($user)) {
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

        if ($ticket->isSubmittedBy($user)) {
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

    /*
     * Whoever may reply may reopen by replying: the requester for a while
     * after the close, the person handling an escalation, and the supporters
     * of the ticket's own panel.
     */
    public function reopen($user, Ticket $ticket): bool
    {
        if (! $ticket->isClosed) {
            return false;
        }

        if ($ticket->isSubmittedBy($user)) {
            return $ticket->isEscalation() || $ticket->isWithinReopenWindow();
        }

        return $this->manage($user, $ticket);
    }

    /*
     * Each side deletes the tickets that live in its own panel, and a
     * requester never does.
     */
    public function delete($user, Ticket $ticket): bool
    {
        return $ticket->isInCurrentPanel() && $this->manage($user, $ticket);
    }

    /*
     * A queue worker may not register the ticket's panel, so it finds the
     * plugin rather than requiring it.
     */
    private function isSupporter($user, Ticket $ticket): bool
    {
        $viewer = ConversationViewer::current();

        if ($ticket->panel === $viewer->panelId && (string) $viewer->userId === (string) $user->getAuthIdentifier()) {
            return $viewer->isSupporter;
        }

        $supportersQuery = TicketPlugin::find($ticket->panel)?->getAllSupportersQuery();

        if ($supportersQuery === null) {
            return false;
        }

        return app()->call($supportersQuery)
            ->whereKey($user->getAuthIdentifier())
            ->exists();
    }
}
