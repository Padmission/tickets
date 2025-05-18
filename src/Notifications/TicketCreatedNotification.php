<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;

class TicketCreatedNotification extends Notification
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
        return (new MailMessage)
            ->subject(__('padmission-tickets::notifications.ticket-created.subject'))
            ->line(__('padmission-tickets::notifications.ticket-created.message', ['subject' => $this->ticket->subject]))
            ->action(__('padmission-tickets::notifications.ticket-created.action'), TicketResource::getUrl('view', ['record' => $this->ticket]));
    }
}
