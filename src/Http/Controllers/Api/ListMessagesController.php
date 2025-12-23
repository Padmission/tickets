<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Http\DataMappers\TicketActivityMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\TicketPlugin;

class ListMessagesController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, $ticket)
    {
        // Remove global scopes to find the ticket and get its panel
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $ticketRecord = $ticketModel::withoutGlobalScopes()->findOrFail($ticket);

        // Get the plugin for this ticket's panel and verify against custom query
        $panelPlugin = TicketPlugin::get($ticketRecord->panel);
        /** @var Ticket $ticket */
        $ticket = $panelPlugin->getTicketQuery()->findOrFail($ticket);

        resolve(TicketAuth::class)->authorizeTicketAccess($ticket, $request->user());

        $activityService = resolve(TicketActivityService::class);

        $messages = $activityService->getActivities($ticket, $request->integer('offset'));

        return [
            'ticket' => [
                'status' => $ticket->status->display_name,
                'is_closed' => $ticket->isClosed,
            ],
            'messages' => $messages->values()->map(fn ($message) => TicketActivityMapper::map($message)),
        ];
    }
}
