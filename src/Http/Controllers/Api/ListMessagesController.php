<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityVisibility;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;

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
            ->when(
                $request->has('offset'),
                fn (Builder $query) => $query->where('id', '>', $request->integer('offset'))
            )
            ->get()
            ->when(
                $request->user()->cannot('viewAny'),
                fn (Collection $collection) => $collection->filter(
                    fn (TicketActivity $message) => $message->visibility === ActivityVisibility::Public
                )
            )
            ->map(function (TicketActivity $message) use ($currentSender) {
                $message->side = match (true) {
                    $message->sender === ActivitySender::System => ActivitySide::System,
                    $message->sender === $currentSender => ActivitySide::Me,
                    default => ActivitySide::Other,
                };

                return $message;
            });

        return [
            'ticket' => [
                'status' => $ticket->status->display_name,
                'is_closed' => $ticket->isClosed,
            ],
            'messages' => $messages->values(),
        ];
    }
}
