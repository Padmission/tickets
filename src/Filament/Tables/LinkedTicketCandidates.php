<?php

namespace Padmission\Tickets\Filament\Tables;

use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class LinkedTicketCandidates
{
    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function parents(Builder $query, Ticket $ticket): Builder
    {
        return static::scope($query, $ticket, 'parentTicket', TicketPlugin::get($ticket->panel)->getLinkedTicketParentPanels());
    }

    /**
     * Escalations this ticket's panel opened (by source, or through an
     * original it sent), so a target panel's own tickets that were never
     * escalations are not offered as ones to join.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function openEscalations(Builder $query, Ticket $ticket): Builder
    {
        $model = $query->getModel();

        return static::parents($query, $ticket)
            ->whereNull($query->qualifyColumn('closed_at'))
            ->escalations()
            ->where(fn (Builder $query): Builder => $query
                ->where($query->qualifyColumn('source_panel'), $ticket->panel)
                ->orWhereExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                    ->selectRaw('1')
                    ->from($model->getTable(), 'panel_originals')
                    ->whereColumn('panel_originals.linked_ticket_id', $model->getQualifiedKeyName())
                    ->where('panel_originals.panel', $ticket->panel)));
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function children(Builder $query, Ticket $ticket): Builder
    {
        return static::scope($query, $ticket, 'childTickets', TicketPlugin::get($ticket->panel)->getLinkedTicketChildPanels())
            ->where(fn (Builder $query) => $query
                ->whereNull($query->qualifyColumn('linked_ticket_id'))
                ->orWhere($query->qualifyColumn('linked_ticket_id'), $ticket->getKey()));
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<Panel>  $panels
     * @return Builder<Ticket>
     */
    protected static function scope(Builder $query, Ticket $ticket, string $relation, array $panels): Builder
    {
        // A cross-tenant panel lifts the host's tenant scope (pinned to the
        // viewer) from ticket relationships; the tenant match below then keeps
        // candidates to the ticket's own tenant. Gated like the panel-aware
        // relations so the picker and the field's loaded state scope alike.
        $modifier = TicketPlugin::get()->getRelationshipScopeModifier()
            ? TicketPlugin::get($ticket->panel)->getRelationshipScopeModifier()
            : null;

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => $relation]);
        }

        $query
            ->whereKeyNot($ticket->getKey())
            ->whereIn($query->qualifyColumn('panel'), array_map(fn (Panel $panel): string => $panel->getId(), $panels));

        // A ticket without a tenant matches only tenant-less tickets, never every tenant.
        if (config('padmission-tickets.tenancy.enabled')) {
            $query->where($query->qualifyColumn('tenant_id'), $ticket->getAttribute('tenant_id'));
        }

        return $query;
    }
}
