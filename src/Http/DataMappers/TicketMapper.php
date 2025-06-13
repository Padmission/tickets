<?php

namespace Padmission\Tickets\Http\DataMappers;

use Padmission\Tickets\Models\Ticket;

class TicketMapper
{
    /**
     * @param  Ticket  $ticket
     */
    public static function map($ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'latest_message' => str($ticket->latestMessage?->content)->stripTags()->words(20),
            'is_closed' => $ticket->isClosed,
            'updated_at' => $ticket->updated_at->diffForHumans(),
        ];
    }
}
