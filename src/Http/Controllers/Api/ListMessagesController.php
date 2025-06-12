<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Http\DataMappers\TicketActivityMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;

class ListMessagesController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, Ticket $ticket)
    {
        $currentSender = $request->user()->id === $ticket->submitter_id
            ? ActivitySender::User
            : ActivitySender::Supporter;

        $isAuthorized = $currentSender !== ActivitySender::User
            || auth()->user()->can('manage', $ticket);

        abort_unless($isAuthorized, 403);

        $messages = $ticket
            ->ticketActivities()
            ->when(
                $request->has('offset'),
                fn (Builder $query) => $query->where('id', '>', $request->integer('offset'))
            )
            ->get()
            ->filter(fn (TicketActivity $activity) => $this->shouldShowActivity($activity))
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
            'messages' => $messages->values()->map(fn ($message) => TicketActivityMapper::map($message)),
        ];
    }

    protected function shouldShowActivity(TicketActivity $activity): bool
    {
        // TODO: Add correct policy
        if (auth()->user()->can('viewAny')) {
            return true;
        }

        return in_array($activity->type, [ActivityType::Closed, ActivityType::Message, ActivityType::Closed]);
    }
}
