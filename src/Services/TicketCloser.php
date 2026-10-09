<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\ScopesLookupsToTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\TicketPlugin;

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
     * A deleted disposition is never offered, though a closed ticket still
     * shows its own through the relation that keeps them.
     *
     * @param  Builder<TicketDisposition>|null  $query  The field's own builder, which has to stay the one it was given.
     * @return Builder<TicketDisposition>
     */
    public function dispositionsFor(Ticket $ticket, ?Builder $query = null): Builder
    {
        if ($query === null) {
            /** @var Builder<TicketDisposition> */
            return TicketPlugin::resolveModelClass(TicketDisposition::class)::optionsForTicket($ticket)->withoutTrashed();
        }

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
