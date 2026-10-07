<?php

namespace Padmission\Tickets\Support;

use Padmission\Tickets\Models\Ticket;

class TicketOrganization
{
    public static function shouldScope(Ticket $ticket): bool
    {
        // A populated tenant always applies. With tenancy enabled, a null tenant
        // matches only tenant-less rows. With both absent, keep the host's scope.
        return filled($ticket->getAttribute('tenant_id')) || config('padmission-tickets.tenancy.enabled');
    }
}
