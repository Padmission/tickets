<?php

namespace Padmission\Tickets\Services;

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

/*
 * Reassign and Edit both change who a ticket is assigned to, through this one
 * path, so who may be picked, the history note and the assignment email stay
 * the same in both.
 */
class TicketReassignment
{
    /**
     * The ticket goes to the supporters query so a host can scope it to the
     * ticket, such as its tenant, rather than to whoever is looking.
     *
     * @return array<int|string, string>
     */
    public function eligible(Ticket $ticket): array
    {
        $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

        if (! $allSupportersQuery) {
            return [];
        }

        return app()->call($allSupportersQuery, ['ticket' => $ticket])
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function choices(Ticket $ticket): array
    {
        return collect($this->eligible($ticket))->except($ticket->assignee_id)->all();
    }

    public function isEligible(Ticket $ticket, mixed $assigneeId): bool
    {
        return (is_int($assigneeId) || is_string($assigneeId)) && array_key_exists($assigneeId, $this->eligible($ticket));
    }

    /*
     * Saved through the model, so the observer writes the history note and
     * the assigned event emails the new assignee.
     */
    public function assign(Ticket $ticket, mixed $assigneeId): bool
    {
        if (! $this->isEligible($ticket, $assigneeId)) {
            return false;
        }

        $ticket->update(['assignee_id' => $assigneeId]);

        return true;
    }
}
