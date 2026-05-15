<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\TicketPlugin;

class MarkTicketSeenController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, $ticket)
    {
        $validated = $request->validate([
            'last_seen_activity_id' => ['required', 'exists:ticket_activities,id'],
        ]);

        // Remove global scopes to find the ticket and get its panel
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $ticketRecord = $ticketModel::withoutGlobalScopes()->findOrFail($ticket);

        // Get the plugin for this ticket's panel and verify against custom query
        $panelPlugin = TicketPlugin::get($ticketRecord->panel);
        /** @var Ticket $ticket */
        $ticket = $panelPlugin->getTicketQuery()->findOrFail($ticket);

        app(TicketAuth::class)->authorizeTicketAccess($ticket, $request->user());

        // Verify activity belongs to this ticket
        $ticket->ticketActivities()
            ->where('id', $validated['last_seen_activity_id'])
            ->firstOrFail();

        // Update last seen
        app(TicketActivityService::class)->markAsSeen(
            $ticket,
            $request->user(),
            $validated['last_seen_activity_id']
        );

        return response()->json(['success' => true]);
    }
}
