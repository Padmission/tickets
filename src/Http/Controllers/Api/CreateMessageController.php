<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Tiptap\Editor;

class CreateMessageController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, Ticket $ticket)
    {
        $this->authorize('create', $ticket);

        $validated = $request->validate([
            'content' => 'required|string',
            'lock_turn' => 'boolean',
        ]);

        $content = (new Editor)->sanitize($validated['content']);

        $activity = $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => $request->user()->id === $ticket->submitter_id
                ? ActivitySender::User
                : ActivitySender::Supporter,

            'content' => $content,
        ]);

        $activity->side = ActivitySide::Me;

        $this->handleTurnChange($ticket, $activity, $validated['lock_turn']);

        return [
            'message' => $activity,
        ];
    }

    protected function handleTurnChange(Ticket $ticket, TicketActivity $activity, bool $lockTurn = false): void
    {
        $currentTurn = $ticket->turn;

        $nextTurn = match (true) {
            $lockTurn => $currentTurn,
            $activity->sender === ActivitySender::Supporter => Turn::User,
            $activity->sender === ActivitySender::User => Turn::Supporter,
            default => $currentTurn,
        };

        if ($currentTurn !== $nextTurn) {
            $ticket->ticketActivities()->create([
                'type' => ActivityType::TurnChanged,
                'sender' => ActivitySender::System,
                'data' => [
                    'from' => $currentTurn,
                    'to' => $nextTurn,
                ],
            ]);

            $ticket->update([
                'turn' => $nextTurn,
            ]);
        }
    }
}
