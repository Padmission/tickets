<?php

namespace Padmission\Tickets\Http\DataMappers;

use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;

class TicketMapper
{
    public static function map(Ticket $ticket, ?Authenticatable $user = null): array
    {
        return [
            'id' => $ticket->id,
            'subject' => html_entity_decode($ticket->subject, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'status' => TicketStatusMapper::map($ticket->status),
            'latest_message' => $ticket->latestMessage?->plainTextContent(20),
            'is_closed' => $ticket->isClosed,
            'needs_attention' => $ticket->waitingOn() === Turn::User,
            'is_unread' => $user ? $ticket->hasUnreadMessagesFor($user) : false,
            'updated_at' => $ticket->updated_at->diffForHumans(),
        ];
    }
}
