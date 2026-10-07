<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Http\DataMappers\TicketMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class ListTicketsController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $this->authorize('create', $ticketModel);

        $query = $ticketModel::query()
            ->where('submitter_id', $request->user()->id)
            ->withoutEscalations();

        $hasClosedTickets = (clone $query)->closed()->exists();

        $tickets = $query
            ->with(['latestMessage', 'ticketUserStates', 'ticketActivities'])
            ->when(! $request->boolean('include_closed'), fn ($query) => $query->open())
            ->orderBy('updated_at', 'desc')
            ->get();

        return [
            'tickets' => $tickets->map(fn ($ticket) => TicketMapper::map($ticket, $request->user())),
            'has_closed_tickets' => $hasClosedTickets,
        ];
    }
}
