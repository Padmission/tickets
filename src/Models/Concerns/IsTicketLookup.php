<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Support\TicketOrganization;
use Padmission\Tickets\TicketPlugin;

/*
 * Where every status, priority and disposition offered as a choice comes from.
 * A ticket's own relation lifts the current panel scope so the ticket can show
 * the row whichever panel opened it gave, which makes that relation the wrong
 * source for a list of choices: it reaches every panel's rows. These ask the
 * lookup's own table instead, for one panel and one organization.
 */
trait IsTicketLookup
{
    /**
     * @return Builder<static>
     */
    public static function optionsForTicket(Ticket $ticket): Builder
    {
        return static::optionsForPanel(
            $ticket->panel,
            TicketOrganization::shouldScope($ticket) ? $ticket->getAttribute('tenant_id') : false,
        );
    }

    /**
     * @param  mixed  $tenantId  False leaves the organization unscoped; null matches rows with none.
     * @return Builder<static>
     */
    public static function optionsForPanel(?string $panel, mixed $tenantId = false): Builder
    {
        $query = static::query()->withoutGlobalScope(CurrentPanelScope::class);

        if (filled($panel)) {
            $query->where($query->getModel()->qualifyColumn('panel'), $panel);
        }

        if ($tenantId !== false) {
            $query->where('tenant_id', $tenantId);
        }

        $modifier = TicketPlugin::find($panel)?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => static::lookupName()]);
        }

        return $query;
    }

    abstract protected static function lookupName(): string;
}
