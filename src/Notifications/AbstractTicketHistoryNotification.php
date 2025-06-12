<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketNotification;

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
        $lastNotification = $this->getLastNotification($notifiable);

        $maxEvents = 10;
        $maxDays = 7;

        $activities = $this->getUnreadActions($notifiable, $maxEvents, $maxDays);

        if ($lastNotification) {
            $lastNotification->update([
                'updated_at' => now(),
            ]);
        } else {
            $this->ticket->ticketNotifications()->create([
                'user_id' => $notifiable->getKey(),
            ]);
        }

        $hasMoreActivities = $activities->count() >= $maxEvents;

        $styles = $this->getStyles();

        $message = (new MailMessage)
            ->subject($this->getEmailSubject())
            ->line(__('padmission-tickets::notifications.ticket-history.intro'))
            ->action(__('padmission-tickets::notifications.ticket-history.action'), $this->getActionUrl());

        if ($hasMoreActivities) {
            $message->line(__('padmission-tickets::notifications.ticket-history.more-activities'));
        }

        $message->line(__('padmission-tickets::notifications.ticket-history.outro'));

        return $message
            ->view($this->getEmailView(), [
                'ticket' => $this->ticket,
                'activitiesHeader' => __('padmission-tickets::notifications.ticket-history.activities-header'),
                'activities' => $activities,
                'lastNotificationDate' => $lastNotification?->created_at,
                'totalActivities' => $activities->count(),
                'hasMoreActivities' => $hasMoreActivities,
                'maxDays' => $maxDays,
                'maxEvents' => $maxEvents,
                'styles' => $styles,
            ]);
    }

    public function getLastNotification($notifiable) : ?TicketNotification {
        return once(function() use ($notifiable) {
            return $this->ticket
                ->ticketNotifications()
                ->where('user_id', $notifiable->getKey())
                ->latest()
                ->first();
        });
    }

    public function getUnreadActions($notifiable, int $maxEvents, int $maxDays) : Collection {
        return once(function() use ($notifiable, $maxEvents, $maxDays){
            $lastNotification = $this->getLastNotification($notifiable);
            return $this->ticket
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
        });
    }

    public function getActionUrl() : string {
        $data = (array)$this->ticket->data;

        $basis = null;

        if (array_key_exists('url', $data) && $data['url']) {
            $basis = $data['url'];
        } else {
            $basis = url('/');
        }

        return $this->addHash($basis, 'ticket-'.$this->ticket->id);
    }

    protected function addHash(string $url, string $hash): string
    {
        validator(['url' => $url], ['url' => 'required|url'])->validate();

        $baseUrl = Str::before($url, '#');
        $cleanHash = Str::start(ltrim($hash, '#'), '#');

        return $baseUrl . $cleanHash;
    }

    /**
     * TODO: This needs to be drastically refactored.  I'm not sure of how we should handle this. We have to bring
     * Laravel's default styles in to our own view, so it's a starting point to get the MVP out for training.
     * @return string
     */

    protected function getStyles(): string
    {
        $styles = '';
        $basePath = base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/themes/default.css');
        if (file_exists($basePath)) {
            $styles = file_get_contents($basePath);
        }
        $styles .= '.inner-body {
            margin-top: 1.25rem;
        }a.button-blue,
a.button-primary { color: #fff; }';

        return $styles;
    }

    public function getEmailSubject() : string {
        return __('padmission-tickets::notifications.ticket-history.subject');
    }

    public function getEmailView() : string {
        return 'padmission-tickets::emails.ticket-history';
    }
}
