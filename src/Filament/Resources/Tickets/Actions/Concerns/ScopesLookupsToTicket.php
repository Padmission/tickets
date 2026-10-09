<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Models\Concerns\IsTicketLookup;
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

        if (! $ticket instanceof Ticket) {
            return $query;
        }

        $model = $query->getModel();
        $lookup = $model::class;

        if (! in_array(IsTicketLookup::class, class_uses_recursive($lookup), true)) {
            return $query;
        }

        // The field keeps the builder it was handed, so the ticket's own option
        // set is applied to it rather than used in its place.
        $key = $model->getQualifiedKeyName();

        return $query->whereIn($key, $lookup::optionsForTicket($ticket)->select($key));
    }
}
