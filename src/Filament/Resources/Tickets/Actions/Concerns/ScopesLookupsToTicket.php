<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;

/*
 * Statuses, priorities and dispositions offered for a ticket come from its own
 * panel and organization. A cross-tenant panel lifts the host's tenant scope
 * from these relations, so without this its staff would see every organization's
 * rows. The organization is the ticket's whenever it has one, whether or not
 * the host has turned tenancy on: those lookups belong to that organization.
 */
trait ScopesLookupsToTicket
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function scopeLookupToTicket(Builder $query, mixed $ticket): Builder
    {
        $query->withoutGlobalScope(CurrentPanelScope::class);

        if ($ticket instanceof Ticket && filled($ticket->panel)) {
            $query->where($query->getModel()->qualifyColumn('panel'), $ticket->panel);
        }

        if ($ticket instanceof Ticket && $this->lookupBelongsToTicketsOrganization($ticket)) {
            $query->where('tenant_id', $ticket->getAttribute('tenant_id'));
        }

        return $query;
    }

    protected function lookupBelongsToTicketsOrganization(Ticket $ticket): bool
    {
        return filled($ticket->getAttribute('tenant_id')) || config('padmission-tickets.tenancy.enabled');
    }
}
