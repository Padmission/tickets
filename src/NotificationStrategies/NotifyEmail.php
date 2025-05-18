<?php

namespace Padmission\Tickets\NotificationStrategies;

use Closure;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;

final class NotifyEmail implements NotificationStrategy
{
    public function __construct(
        public string|Closure $email
    ) {}

    public function notify(Ticket $ticket): void
    {
        $notifiable = (new AnonymousNotifiable)
            ->route('mail', value($this->email));

        Notification::send(
            $notifiable,
            resolve(TicketCreatedNotification::class, [
                'ticket' => $ticket,
            ])
        );
    }
}
