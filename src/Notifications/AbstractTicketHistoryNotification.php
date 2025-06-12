<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Padmission\Tickets\Enums\ActivitySender;
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
        $lastNotification = $this->ticket
            ->ticketNotifications()
            ->where('user_id', $notifiable->getKey())
            ->latest()
            ->first();

        $maxEvents = 10;
        $maxDays = 7;

        $activities = $this->ticket
            ->ticketActivities()
            ->with('user')
            ->where('created_at', '>', now()->subDays($maxDays))
            ->where('created_at', '<=', now())
            ->when($lastNotification, function ($sub) use ($lastNotification) {
                $sub->where('created_at', '>', $lastNotification->created_at);
            })
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

        $styles = $this->getStyles();

        $message = (new MailMessage)
            ->subject(__('padmission-tickets::notifications.ticket-created.subject'))
            ->line(__('padmission-tickets::emails.ticket-history.intro'))
            ->action(__('padmission-tickets::notifications.ticket-created.action'), url('/#test'))
            ->line(__('padmission-tickets::emails.ticket-history.outro'))

            ->view('padmission-tickets::emails.ticket-history', [
                'ticket' => $this->ticket,
                'activitiesHeader' => __('padmission-tickets::emails.ticket-history.activities-header'),
                'activities' => $activities,
                'lastNotificationDate' => $lastNotification?->created_at,
                'totalActivities' => $activities->count(),
                'hasMoreActivities' => $activities->count() >= $maxEvents,
                'maxDays' => $maxDays,
                'maxEvents' => $maxEvents,
                'styles' => $styles
            ]);

        return $message;
    }

    protected function getStyles() : string {
        $styles = '';
        $basePath = base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/themes/default.css');
        if (file_exists($basePath)) {
            $styles = file_get_contents($basePath);
        }
        $styles .= '.inner-body {
            margin-top: 1.25rem;
        }';
        return $styles;
    }
}
