<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Http\DataMappers\TicketActivityMapper;
use Padmission\Tickets\Services\ApiTicketResolver;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Services\TicketReopening;
use Padmission\Tickets\TicketPlugin;

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
                'closed_at' => $ticket->closed_at?->toIso8601String(),
                'reopen_choices' => resolve(TicketReopening::class)->choicesFor($ticket, $request->user()),
                'reply_disabled_reason' => resolve(TicketAuth::class)->replyDisabledReason($ticket, $request->user()),
                'reopen_window_days' => TicketPlugin::find($ticket->panel)?->getReopenWindowDays() ?? TicketPlugin::DEFAULT_REOPEN_WINDOW_DAYS,
            ],
            'messages' => $messages->values()->map(fn ($message) => TicketActivityMapper::map($message)),
        ];
    }
}
