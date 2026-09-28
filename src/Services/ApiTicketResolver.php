<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

/*
 * A host scope on the original's own panel (its tenant, say) hides it from
 * those who work its escalation, so an original is also found when its
 * escalation is. That fallback answers 404, not 403, to anyone who may not
 * read the original, so a hidden ticket's id is not given away.
 */
class ApiTicketResolver
{
    public function resolve(int|string $ticketId, ?Authenticatable $user): Ticket
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        /** @var Ticket $record */
        $record = $ticketModel::withoutGlobalScopes()->findOrFail($ticketId);

        $ticket = TicketPlugin::get($record->panel)->getTicketQuery()->find($ticketId);

        if ($ticket instanceof Ticket) {
            return $ticket;
        }

        abort_unless($this->escalationIsReachable($record) && resolve(TicketAuth::class)->canAccess($record, $user), 404);

        return $record;
    }

    protected function escalationIsReachable(Ticket $original): bool
    {
        if (blank($original->linked_ticket_id)) {
            return false;
        }

        $escalation = TicketPlugin::resolveModelClass(Ticket::class)::withoutGlobalScopes()->find($original->linked_ticket_id);

        if (! $escalation instanceof Ticket) {
            return false;
        }

        return TicketPlugin::find($escalation->panel)
            ?->getTicketQuery()
            ->whereKey($escalation->getKey())
            ->exists() ?? false;
    }
}
