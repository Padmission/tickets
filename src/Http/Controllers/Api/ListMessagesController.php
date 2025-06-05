<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\Ticket;

class ListMessagesController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $currentSender = $request->user()->id === $ticket->submitter_id
            ? ActivitySender::User
            : ActivitySender::Supporter;

        $messages = $ticket
            ->ticketActivities()
            ->when($request->has('offset'), fn ($query) => $query->where('id', '>', $request->integer('offset')))
            ->get();

        if (! $request->user()->can('viewAny')) {
            $messages = $messages->filter(function (TicketActivity $message) {
                return $message->type === ActivityType::Message;
            });
        }

        $messages = $messages->filter(function (TicketActivity $message) {
            return filled($message->content);
        });

        $messages = $messages->map(function (TicketActivity $message) use ($currentSender) {
            $message->side = match (true) {
                $message->sender === ActivitySender::System => 'system',
                $message->sender === $currentSender => 'me',
                default => 'other',
            };

            return $message;
        });

        return [
            'ticket' => [
                'status' => $ticket->status->name,
                'is_closed' => $ticket->isClosed,
            ],
            'messages' => $messages->values(),
        ];
    }
}
