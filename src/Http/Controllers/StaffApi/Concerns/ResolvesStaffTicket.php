<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\ApiTicketResolver;
use Padmission\Tickets\Services\TicketAuth;

trait ResolvesStaffTicket
{
    /*
     * Found the way the chat API finds it, so a ticket the viewer may not
     * read answers 404 or 403 exactly as it does there.
     */
    protected function resolveTicket(int|string $ticketId, Authenticatable $viewer): Ticket
    {
        $ticket = resolve(ApiTicketResolver::class)->resolve($ticketId, $viewer);

        resolve(TicketAuth::class)->authorizeTicketAccess($ticket, $viewer);

        return $ticket;
    }
}
