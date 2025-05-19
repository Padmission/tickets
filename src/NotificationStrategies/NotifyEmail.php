<?php

namespace Padmission\Tickets\NotificationStrategies;

use Closure;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;

final class NotifyEmail implements NotificationStrategy
{
    public function __construct(
        public string|array|Closure $emails
    ) {}

    public function notify(Ticket $ticket): void
    {
        $notifiables = collect(Arr::wrap(value($this->emails)))
            ->map(fn ($email) => (new AnonymousNotifiable)
                ->route('mail', $email)
            );

        Notification::send(
            $notifiables,
            resolve(TicketCreatedNotification::class, [
                'ticket' => $ticket,
            ])
        );
    }
}
