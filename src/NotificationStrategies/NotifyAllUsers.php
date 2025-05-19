<?php

namespace Padmission\Tickets\NotificationStrategies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\TicketPlugin;

final class NotifyAllUsers implements NotificationStrategy
{
    public function notify(Ticket $ticket): void
    {
        // TODO: Only notify users that can handle the ticket
        $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);

        Notification::send(
            $userModel::all(),
            resolve(TicketCreatedNotification::class, [
                'ticket' => $ticket,
            ])
        );
    }
}
