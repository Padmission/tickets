<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Padmission\Tickets\Http\DataMappers\StaffApi\StaffTicketMapper;
use Padmission\Tickets\Models\Ticket;

/*
 * One cheap request a client polls: the tickets that changed or got a new
 * entry since its last call, with the queue counts, and the time to send
 * back next. A ticket's own timestamp misses replies, which only add an
 * activity, so activities are checked too.
 */
class ChangesController
{
    protected const int MAX_TICKETS = 100;

    public function __invoke(Request $request): array
    {
        $validated = $request->validate([
            'since' => ['required', 'date'],
        ]);

        // Read before querying, so anything written while this runs comes back next time too.
        $now = now();
        $since = Carbon::parse($validated['since']);

        $tickets = ListTicketsController::forView('all')
            ->where(fn (Builder $query): Builder => $query
                ->where($query->qualifyColumn('updated_at'), '>', $since)
                ->orWhereHas('ticketActivities', fn (Builder $activities): Builder => $activities->where('created_at', '>', $since)))
            ->with(['status', 'priority', 'assignee', 'submitter', 'latestMessage'])
            ->orderByDesc('tickets.updated_at')
            ->limit(self::MAX_TICKETS + 1)
            ->get();

        return [
            'data' => $tickets->take(self::MAX_TICKETS)
                ->map(fn (Ticket $ticket): array => StaffTicketMapper::summary($ticket, $request->user()))
                ->values()
                ->all(),
            'meta' => [
                'next_since' => $now->toIso8601String(),
                // Past the cap, a client should reload its lists rather than trust the deltas.
                'truncated' => $tickets->count() > self::MAX_TICKETS,
                'counts' => [
                    'needs_you' => ListTicketsController::forView('needs_you')->count(),
                    'mine' => ListTicketsController::forView('mine')->count(),
                    'unassigned' => ListTicketsController::forView('unassigned')->count(),
                ],
            ],
        ];
    }
}
