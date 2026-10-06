<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\ScopesLookupsToTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;

/*
 * One close, whether from a ticket's own Close dialog or from the list's
 * bulk Close, so both leave a ticket exactly the same way.
 */
class TicketCloser
{
    use ScopesLookupsToTicket;

    public function canClose(Ticket $ticket): bool
    {
        return $ticket->panel === Filament::getCurrentOrDefaultPanel()->getId()
            && ! $ticket->isClosed
            && TicketResource::canEdit($ticket);
    }

    /**
     * The relation keeps deleted dispositions, so a closed ticket still shows
     * its own, but a deleted one is never offered.
     *
     * @return Builder<TicketDisposition>
     */
    public function dispositionsFor(Ticket $ticket, ?Builder $query = null): Builder
    {
        /** @var Builder<TicketDisposition> $query */
        $query ??= Relation::noConstraints(fn (): Relation => $ticket->disposition())->getQuery();

        return $this->scopeLookupToTicket($query, $ticket)->withoutTrashed();
    }

    public function close(Ticket $ticket, int|string|null $dispositionId): void
    {
        if (filled($dispositionId) && ! $this->dispositionsFor($ticket)->whereKey($dispositionId)->exists()) {
            throw ValidationException::withMessages([
                'disposition' => __('validation.exists', ['attribute' => __('padmission-tickets::tickets.actions.close.disposition.label')]),
            ]);
        }

        $ticket->close(
            dispositionId: filled($dispositionId) ? (int) $dispositionId : null,
            closedById: Filament::auth()->id(),
        );
    }
}
