<?php

namespace Padmission\Tickets\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

/*
 * The assignee relation carries the acting panel's scopes. A ticket linked
 * into another panel can be assigned to someone only that panel's scopes
 * reveal (a cross-tenant support panel, say), so the owning panel's
 * relationship modifier is applied on top to find them.
 */
class TicketAssignee
{
    public static function for(Ticket $ticket): ?Model
    {
        if (blank($ticket->assignee_id)) {
            return null;
        }

        $relation = $ticket->assignee();
        $modifier = $ticket->isNotInCurrentPanel()
            ? TicketPlugin::find($ticket->panel)?->getRelationshipScopeModifier()
            : null;

        if ($modifier === null) {
            return $relation->first();
        }

        $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'assignee']);

        return static::onlyName($relation->first());
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<int, string>  $panelIds
     * @return Builder<Ticket>
     */
    public static function eagerLoadForForeignPanels(Builder $query, array $panelIds): Builder
    {
        $modifiers = collect($panelIds)
            ->map(fn (string $panelId): ?Closure => TicketPlugin::find($panelId)?->getRelationshipScopeModifier())
            ->filter();

        if ($modifiers->isEmpty()) {
            return $query;
        }

        return $query->with(['assignee' => function (Relation $relation) use ($modifiers): void {
            foreach ($modifiers as $modifier) {
                app()->call($modifier, ['relation' => $relation, 'model' => 'assignee']);
            }

            $relation->afterQuery(fn (Collection $assignees): Collection => $assignees->each(static::onlyName(...)));
        }]);
    }

    /*
     * Found past the viewer's own scopes, and a Livewire page sends its loaded
     * relations to the browser, so only the person's name may be serialized.
     * The attributes stay loaded for display.
     */
    protected static function onlyName(?Model $assignee): ?Model
    {
        return $assignee?->setVisible([$assignee->getKeyName(), 'name']);
    }
}
