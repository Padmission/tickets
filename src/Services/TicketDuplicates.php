<?php

namespace Padmission\Tickets\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketDuplicates
{
    /** @return Builder<Ticket> */
    public function ticketsFor(Ticket $ticket): Builder
    {
        $query = TicketPlugin::get()->getTicketQuery()
            ->where('tickets.panel', $ticket->panel);

        // Also scope a null tenant: a cross-tenant panel must never offer all tenants.
        if (config('padmission-tickets.tenancy.enabled')) {
            $query->where($query->getModel()->qualifyColumn('tenant_id'), $ticket->getAttribute('tenant_id'));
        }

        return $query;
    }

    /** @return Builder<Ticket> */
    public function candidates(Ticket $ticket): Builder
    {
        return $this->ticketsFor($ticket)->whereKeyNot($ticket->getKey());
    }

    public function involvesEscalation(Ticket $ticket): bool
    {
        return filled($ticket->linked_ticket_id) || $ticket->isEscalation();
    }

    /*
     * Re-read every link under a lock. The picker is only a convenience: its
     * selection may be stale or forged, so scope and validate again here.
     */
    public function close(Ticket $ticket, int|string $originalId, int|string|null $dispositionId = null): void
    {
        DB::transaction(function () use ($ticket, $originalId, $dispositionId): void {
            $current = $this->ticketsFor($ticket)->whereKey($ticket->getKey())->lockForUpdate()->first();

            abort_unless($current !== null && resolve(TicketCloser::class)->canClose($current), 403);

            $this->guardEscalation($current);
            $visited = [$current->getKey()];

            do {
                if (in_array($originalId, $visited, false)) {
                    $this->refuse('invalid_original');
                }

                $original = $this->candidates($current)->whereKey($originalId)->lockForUpdate()->first();

                if ($original === null) {
                    $this->refuse('invalid_original');
                }

                $this->guardEscalation($original);
                $visited[] = $original->getKey();
                $originalId = $original->duplicate_of_ticket_id;
            } while (filled($originalId));

            $closer = resolve(TicketCloser::class);
            $dispositions = $closer->dispositionsFor($current);

            if ((filled($dispositionId) && ! (clone $dispositions)->whereKey($dispositionId)->exists())
                || (blank($dispositionId) && $dispositions->exists())) {
                throw ValidationException::withMessages([
                    'disposition' => __('padmission-tickets::tickets.actions.close_as_duplicate.invalid_disposition'),
                ]);
            }

            $current->duplicate_of_ticket_id = $original->getKey();
            $closer->close($current, $dispositionId);
            $current->addTicketActivity(ActivityType::ClosedAsDuplicate, ActivitySender::System, data: ['ticket' => $original->getKey()]);
            $original->addTicketActivity(ActivityType::DuplicatedBy, ActivitySender::System, data: ['ticket' => $current->getKey()]);

            // Keep the page's record in sync without retaining stale relationships.
            $ticket->refresh();
        }, attempts: 3);
    }

    protected function guardEscalation(Ticket $ticket): void
    {
        if ($this->involvesEscalation($ticket)) {
            $this->refuse('escalation');
        }
    }

    protected function refuse(string $reason): never
    {
        throw ValidationException::withMessages([
            'original' => __("padmission-tickets::tickets.actions.close_as_duplicate.{$reason}"),
        ]);
    }
}
