<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;

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

        $activity = $ticket->activities()->create([
            'type' => ActivityType::Message,
            'sender' => $request->user()->id === $ticket->submitter_id
                ? ActivitySender::User
                : ActivitySender::Supporter,
            'content' => $validated['content'],
        ]);

        $currentTurn = $ticket->turn;

        if ($validated['lock_turn'] ?? false) {
            $nextTurn = $currentTurn;
        } else {
            $nextTurn = $currentTurn === Turn::User ? Turn::Supporter : Turn::User;
        }

        if ($currentTurn !== $nextTurn) {
            $ticket->activities()->create([
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

        $activity->side = 'me';

        return [
            'message' => $activity,
        ];
    }
}
