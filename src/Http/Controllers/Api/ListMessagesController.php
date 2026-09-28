<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Http\DataMappers\TicketActivityMapper;
use Padmission\Tickets\Services\ApiTicketResolver;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAuth;

class ListMessagesController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, $ticket)
    {
        $ticket = resolve(ApiTicketResolver::class)->resolve($ticket, $request->user());

        resolve(TicketAuth::class)->authorizeTicketAccess($ticket, $request->user());

        $activityService = resolve(TicketActivityService::class);

        $messages = $activityService->getActivities($ticket, $request->integer('offset'));

        return [
            'ticket' => [
                'subject' => $ticket->subject,
                'status' => $ticket->status->display_name,
                'is_closed' => $ticket->isClosed,
            ],
            'messages' => $messages->values()->map(fn ($message) => TicketActivityMapper::map($message)),
        ];
    }
}
