<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketNotification;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

        $maxEvents = config('padmission-tickets.notification-max-events', 10);
        $maxDays = config('padmission-tickets.notification-max-days', 7);

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

        $logo = $this->getEmailLogo();

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
                'logo' => $logo,
                'totalActivities' => $activities->count(),
                'hasMoreActivities' => $hasMoreActivities,
                'maxDays' => $maxDays,
                'maxEvents' => $maxEvents,
                'styles' => $styles,
            ]);
    }

    /**
     * @param  mixed  $notifiable
     */
    public function getLastNotification($notifiable): ?TicketNotification
    {
        /** @var \Padmission\Tickets\Models\TicketNotification|null $notification */
        $notification = $this->ticket
            ->ticketNotifications()
            ->where('user_id', $notifiable->getKey())
            ->latest()
            ->first();

        return $notification;
    }

    public function getUnreadActions($notifiable, int $maxEvents, int $maxDays): Collection
    {
        return once(function () use ($notifiable, $maxEvents, $maxDays) {
            $lastNotification = $this->getLastNotification($notifiable);

            return $this->ticket
                ->ticketActivities()
                ->with('user')
                ->where('created_at', '>', now()->subDays($maxDays))
                ->where('created_at', '<=', now())
                ->when($lastNotification, function ($sub) use ($lastNotification) {
                    $sub->where('created_at', '>', $lastNotification->updated_at);
                })
                ->orderBy('created_at', 'asc')
                ->limit($maxEvents)
                ->get();
        });
    }

    public function getActionUrl(): string
    {
        $data = (array) $this->ticket->data;

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
        try {
            validator(['url' => $url], ['url' => 'required|url'])->validate();
        } catch (ValidationException $e) {
            // Fallback to app URL if ticket URL is invalid
            $url = config('app.url');
        }

        $baseUrl = Str::before($url, '#');
        $cleanHash = Str::start(ltrim($hash, '#'), '#');

        return $baseUrl.$cleanHash;
    }

    protected function getStyles(): string
    {
        return $this->getCoreMailStyles().$this->getTicketCustomStyles();
    }

    private function getCoreMailStyles(): string
    {
        return Cache::remember(__METHOD__, 3600, function () {
            $path = base_path('vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/themes/default.css');

            return file_exists($path) ? file_get_contents($path) : '';
        });
    }

    private function getTicketCustomStyles(): string
    {
        return '
        .inner-body { margin-top: 1.25rem; }
        a.button-blue, a.button-primary { color: #fff; }
        .ticket-activity { margin-bottom: 1rem; padding: 1rem; border-left: 4px solid #e5e7eb; }
        .activity-meta { font-size: 0.9em; color: #666; margin-bottom: 0.5rem; }
        .activity-content { line-height: 1.5; }
    ';
    }

    public function getEmailSubject(): string
    {
        return __('padmission-tickets::notifications.ticket-history.subject', [
            'subject' => $this->ticket->subject,
            'ticket_id' => $this->ticket->id,
        ]);
    }

    public function getEmailView(): string
    {
        return 'padmission-tickets::emails.ticket-history';
    }

    /**
     * @param  mixed  $logo  // Could be Media object, SVG string, URL, etc.
     */
    protected function resolveLogo(mixed $logo): ?string
    {
        if (is_object($logo)) {
            if (method_exists($logo, 'getLogo')) {
                return $logo->getLogo();
            }
            if (method_exists($logo, 'getUrl')) {
                return $logo->getUrl();
            }
        }

        // If it's a string, check if it's an SVG or a URL
        if (is_string($logo)) {
            // Simple SVG check (starts with <svg)
            if (stripos($logo, '<svg') === 0) {
                return $logo; // raw SVG
            }

            return $logo;
        }

        return null;
    }

    public function getEmailLogo(): ?string
    {
        $panelId = $this->ticket->panel;
        if (! $panelId) {
            return null;
        }

        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant && method_exists($tenant, 'getLogo')) {
            try {
                $logo = $tenant->getLogo();
                $resolved = $this->resolveLogo($logo);

                if (is_string($resolved)) {
                    if (filter_var($resolved, FILTER_VALIDATE_URL)) {
                        return sprintf('<img src="%s" />', $resolved);
                    }

                    return $resolved; // SVG or other string
                }
            } catch (\Throwable $e) {
                // Optionally log or ignore
            }
        }

        // Fallback: get the panel's brand logo
        if ($panel = \Filament\Facades\Filament::getPanel($panelId)) {
            if ($logo = $panel->getBrandLogo()) {
                $height = $panel->getBrandLogoHeight();
                if (filter_var($logo, FILTER_VALIDATE_URL)) {
                    return sprintf('<img src="%s" style="height: %s;" />', $logo, $height);
                }

                return $logo;
            }
        }

        return null;
    }
}
