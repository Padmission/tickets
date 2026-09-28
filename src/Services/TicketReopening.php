<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;

/*
 * What a reply on a closed ticket may do for its writer. A requester may
 * reopen their own ticket for a while after it closed, or start a new one
 * that links back; anyone else who may reopen it does so by replying.
 */
class TicketReopening
{
    public const string REOPEN = 'reopen';

    public const string NEW_TICKET = 'new';

    /**
     * @return list<self::REOPEN|self::NEW_TICKET>
     */
    public function choicesFor(Ticket $ticket, ?Authenticatable $user): array
    {
        if (! $ticket->isClosed || $user === null) {
            return [];
        }

        $gate = Gate::forUser($user);

        if (! $this->isRequester($ticket, $user)) {
            return $gate->allows('reopen', $ticket) ? [self::REOPEN] : [];
        }

        return array_values(array_filter([
            $gate->allows('reopen', $ticket) ? self::REOPEN : null,
            $gate->allows('create', $ticket::class) ? self::NEW_TICKET : null,
        ]));
    }

    public function isRequester(Ticket $ticket, Authenticatable $user): bool
    {
        return $ticket->isSubmittedBy($user) && ! $ticket->isEscalation();
    }
}
