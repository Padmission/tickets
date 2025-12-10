<?php

namespace Padmission\Tickets\Http\DataMappers;

use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;

class TicketMapper
{
    public static function map(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => TicketStatusMapper::map($ticket->status),
            'latest_message' => str($ticket->latestMessage?->content)->stripTags()->words(20),
            'is_closed' => $ticket->isClosed,
            'needs_attention' => $ticket->turn === Turn::User,
            'updated_at' => $ticket->updated_at->diffForHumans(),
        ];
    }
}
