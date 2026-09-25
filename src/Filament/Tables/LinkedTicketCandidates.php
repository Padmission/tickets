<?php

namespace Padmission\Tickets\Filament\Tables;

use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class LinkedTicketCandidates
{
    public static function parents(Builder $query, Ticket $ticket): Builder
    {
        return static::scope($query, $ticket, TicketPlugin::get($ticket->panel)->getLinkedTicketParentPanels())
            ->whereNull($query->qualifyColumn('linked_ticket_id'))
            ->whereNotIn($query->getModel()->getQualifiedKeyName(), static::ticketQuery()
                ->whereNotNull('linked_ticket_id')
                ->whereKeyNot($ticket->getKey())
                ->select('linked_ticket_id'));
    }

    public static function children(Builder $query, Ticket $ticket): Builder
    {
        return static::scope($query, $ticket, TicketPlugin::get($ticket->panel)->getLinkedTicketChildPanels())
            ->where(fn (Builder $query) => $query
                ->whereNull($query->qualifyColumn('linked_ticket_id'))
                ->orWhere($query->qualifyColumn('linked_ticket_id'), $ticket->getKey()));
    }

    /**
     * @param  array<Panel>  $panels
     */
    protected static function scope(Builder $query, Ticket $ticket, array $panels): Builder
    {
        // A cross-tenant panel lifts the host's tenant scope from its
        // relationships; the explicit tenant match below then keeps the
        // candidates to the ticket's own tenant in every panel.
        $modifier = TicketPlugin::find($ticket->panel)?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'linkedTicket']);
        }

        $query
            ->whereKeyNot($ticket->getKey())
            ->whereIn($query->qualifyColumn('panel'), array_map(fn (Panel $panel): string => $panel->getId(), $panels));

        if (config('padmission-tickets.tenancy.enabled') && filled($ticket->getAttribute('tenant_id'))) {
            $query->where($query->qualifyColumn('tenant_id'), $ticket->getAttribute('tenant_id'));
        }

        return $query;
    }

    protected static function ticketQuery(): Builder
    {
        return TicketPlugin::resolveModelClass(Ticket::class)::query()->withoutGlobalScopes();
    }
}
