<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;

/*
 * Statuses, priorities and dispositions offered for a ticket come from its own
 * panel and tenant. A cross-tenant panel lifts the host's tenant scope from
 * these relations, so without this its staff would see every tenant's rows.
 */
trait ScopesLookupsToTicket
{
    protected function scopeLookupToTicket(Builder $query, mixed $ticket): Builder
    {
        $query->withoutGlobalScope(CurrentPanelScope::class);

        if ($ticket instanceof Ticket && filled($ticket->panel)) {
            $query->where($query->getModel()->qualifyColumn('panel'), $ticket->panel);
        }

        if (
            $ticket instanceof Ticket
            && config('padmission-tickets.tenancy.enabled')
            && filled($ticket->getAttribute('tenant_id'))
        ) {
            $query->where($query->getModel()->qualifyColumn('tenant_id'), $ticket->tenant_id);
        }

        return $query;
    }
}
