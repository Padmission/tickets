<?php

namespace Padmission\Tickets\NotificationStrategies;

use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;

final class NotifyAssignedUser implements NotificationStrategy
{
    public function notify(Ticket $ticket): void
    {
        $ticket->loadMissing('assignee');

        Notification::send(
            $ticket->assignee,
            resolve(TicketCreatedNotification::class, [
                'ticket' => $ticket,
            ])
        );
    }
}
