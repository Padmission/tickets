<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification
{
    public function __construct(
        public string $otp,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('padmission-tickets::notifications.email-verification.subject'))
            ->line(__('padmission-tickets::notifications.email-verification.message'))
            ->line(view('padmission-tickets::mails.otp', ['code' => $this->otp]));
    }
}
