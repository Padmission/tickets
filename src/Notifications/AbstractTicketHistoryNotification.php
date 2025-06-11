<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;

abstract class AbstractTicketHistoryNotification extends Notification
{
    public function __construct(
        public Ticket $ticket,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        /**
         * Determine the last time this user looked at this ticket......
         */
        $lastNotification = $this->ticket
            ->ticketNotifications()
            ->where('user_id', $notifiable->getKey())
            ->latest();

        /**
         * Now load any history since then.
         */
        $maxEvents = 10;
        $maxDays = 7;

        $activities = $this->ticket
            ->ticketActivities()
            ->with('user')
            ->where('created_at', '>', now()->subDays($maxDays))
            ->where('created_at', '<=', now())
            ->where('id', '>', $lastNotification->first()?->id ?? 0)
            ->orderBy('created_at', 'asc')
            ->limit($maxEvents)
            ->get()
            ->map(function (TicketActivity $message) use ($notifiable) {
                $message->side = match (true) {
                    $message->sender === ActivitySender::System => 'system',
                    $message->sender === $notifiable => 'me',
                    default => 'other',
                };

                return $message;
            });

        if ($lastNotification) {
            $lastNotification->update([
                'updated_at' => now(),
            ]);
        } else {
            $this->ticket->ticketNotifications()->create([
                'user_id' => $notifiable->getKey(),
            ]);
        }

        $message = (new MailMessage)
            ->subject(__('padmission-tickets::notifications.ticket-created.subject'));

        foreach ($activities as $activity) {
            /**
             * TODO: These need to be built out.
             */
            $message->line($activity->content);
        }

        /**
         * TODO: Talk with Dennis to see how he wants to get anchors to the chat.
         */
        //        dd($this->ticket->panel);
        //      ->action(__('padmission-tickets::notifications.ticket-created.action'), TicketResource::getUrl('view', ['record' => $this->ticket]));

        return $message;
    }
}
