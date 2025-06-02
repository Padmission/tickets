<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class ListTicketsController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $tickets = TicketPlugin::resolveModelClass(Ticket::class)::query()
            ->with('latestActivity')
            ->where('submitter_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return [
            'tickets' => $tickets
                ->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'latest_activity' => str($ticket->latestActivity?->content)
                        ->stripTags()
                        ->words(20),
                    'updated_at' => $ticket->updated_at->diffForHumans(),
                    'is_closed' => $ticket->isClosed,
                ]),
        ];
    }
}
