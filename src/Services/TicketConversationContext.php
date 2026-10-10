<?php

namespace Padmission\Tickets\Services;

use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Models\Ticket;

/*
 * Read-only background a host can show above a ticket's chat, such as the
 * conversation the ticket came from. The viewer has already been authorized
 * for the ticket; what a section may reveal is for the host to decide.
 */
class TicketConversationContext
{
    /**
     * @return list<array{heading: string, html: string}>
     */
    public function sectionsFor(Ticket $ticket, Model $viewer): array
    {
        return [];
    }
}
