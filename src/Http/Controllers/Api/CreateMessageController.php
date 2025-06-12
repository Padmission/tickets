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
use Padmission\Tickets\TicketPlugin;
use Tiptap\Editor;

class CreateMessageController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, int $ticket): array
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $this->authorize('create', $ticketModel);

        $validated = $request->validate([
            'content' => 'required|string',
            'lock_turn' => 'boolean',
        ]);

        $ticket = $ticketModel::findOrFail($ticket);

        $messages = [];
        $content = (new Editor)->sanitize($validated['content']);

        $isFirstActivity = ! $ticket->ticketActivities()->exists();

        if ($isFirstActivity) {
            $this->createFirstMessage($ticket);
        }

        $activity = $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => $request->user()->id === $ticket->submitter_id
                ? ActivitySender::User
                : ActivitySender::Supporter,

            'content' => $content,
        ]);

        $activity->side = ActivitySide::Me;
        $messages[] = $activity;

        $this->handleTurnChange($ticket, $activity, $validated['lock_turn']);

        if ($isFirstActivity) {
            $messages[] = $this->createAutoResponse($ticket);
        }

        return [
            'messages' => $messages,
        ];
    }

    /**
     * @param  Ticket  $ticket
     * @param  TicketActivity  $activity
     */
    protected function handleTurnChange($ticket, $activity, bool $lockTurn = false): void
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

    /**
     * @param  Ticket|null  $ticket
     */
    protected function createFirstMessage($ticket = null)
    {
        $config = TicketPlugin::get()->getChatWidgetConfig();

        return $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => ActivitySender::System,
            'content' => $config->getIntroMessage(),
        ]);
    }

    /**
     * @param  Ticket|null  $ticket
     */
    protected function createAutoResponse($ticket = null)
    {
        // TODO: Make this independent from Filament
        $config = TicketPlugin::get()->getChatWidgetConfig();

        $activity = $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => ActivitySender::System,
            'content' => $config->getAutoResponse(),
        ]);

        $activity->side = ActivitySide::System;

        return $activity;
    }
}
