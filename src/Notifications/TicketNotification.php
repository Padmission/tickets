<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\EmailLogoService;
use Padmission\Tickets\Services\EmailStyleService;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketUrlService;

class TicketNotification extends Notification
{
    public function __construct(
        protected Ticket $ticket,
        protected string $notificationType,
        protected ?TicketActivityService $activityService = null,
        protected ?EmailLogoService $logoService = null,
        protected ?EmailStyleService $styleService = null,
        protected ?TicketUrlService $urlService = null
    ) {
        // Use app() for dependency injection if services not provided
        $this->activityService ??= app(TicketActivityService::class);
        $this->logoService ??= app(EmailLogoService::class);
        $this->styleService ??= app(EmailStyleService::class);
        $this->urlService ??= app(TicketUrlService::class);
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $maxEvents = config('padmission-tickets.notification-max-events', 10);
        $maxDays = config('padmission-tickets.notification-max-days', 7);

        $activities = $this->activityService->getUnreadActivities(
            $this->ticket,
            $notifiable,
            $maxEvents,
            $maxDays
        );

        $latestActivity = $activities->last();

        if ($latestActivity) {
            $this->activityService->markNotificationSent($this->ticket, $notifiable, $latestActivity->id);
        }

        $hasMoreActivities = $activities->count() >= $maxEvents;

        $key = "padmission-tickets::notifications.ticket-{$this->notificationType}.intro";
        $message = (new MailMessage)
            ->subject($this->getEmailSubject())
            ->line(__($key))
            ->action(
                __('padmission-tickets::notifications.ticket-history.action'),
                $this->urlService->getActionUrl($this->ticket)
            );

        if ($hasMoreActivities) {
            $message->line(__('padmission-tickets::notifications.ticket-history.more-activities'));
        }

        $message->line(__('padmission-tickets::notifications.ticket-history.outro'));

        return $message->view($this->getView(), [
            'ticket' => $this->ticket,
            'activitiesHeader' => __('padmission-tickets::notifications.ticket-history.activities-header'),
            'activities' => $activities,
            'lastNotificationDate' => $this->activityService->getLastSeen($this->ticket, $notifiable)?->lastNotifiedActivity?->created_at,
            'logo' => $this->logoService->getEmailLogo($this->ticket),
            'totalActivities' => $activities->count(),
            'hasMoreActivities' => $hasMoreActivities,
            'maxDays' => $maxDays,
            'maxEvents' => $maxEvents,
            'styles' => $this->styleService->getStyles(),
        ]);
    }

    public function getView(): string
    {
        return 'padmission-tickets::emails.ticket-history';
    }

    protected function getEmailSubject(): string
    {
        $key = "padmission-tickets::notifications.ticket-{$this->notificationType}.subject";

        return __($key, [
            'subject' => $this->ticket->subject,
            'ticket_id' => $this->ticket->id,
        ]);
    }
}
