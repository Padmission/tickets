<?php

namespace Padmission\Tickets\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketUrlService;

class TicketNotification extends Notification
{
    public $notificationType;

    public function __construct(
        protected Ticket $ticket,
        protected $event,
    ) {
        $this->notificationType = str($this->event::class)
            ->afterLast('\\')
            ->replace('Ticket', '')
            ->replace('Event', '')
            ->lower()
            ->toString();
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend($notifiable): bool
    {
        $activityService = resolve(TicketActivityService::class);
        $maxEvents = config('padmission-tickets.notification-max-events', 10);

        $activities = $activityService->getUnreadActivities(
            $this->ticket,
            $notifiable,
            $maxEvents,
        );

        return $activities->isNotEmpty();
    }

    public function toMail($notifiable): MailMessage
    {
        $activityService = resolve(TicketActivityService::class);
        $urlService = resolve(TicketUrlService::class);

        $maxEvents = config('padmission-tickets.notification-max-events', 10);

        $activities = $activityService->getUnreadActivities(
            $this->ticket,
            $notifiable,
            $maxEvents,
        );

        $latestActivity = $activities->last();

        if ($latestActivity) {
            $activityService->markAsSent($this->ticket, $notifiable, $latestActivity->id);
        }

        $hasMoreActivities = $activities->count() > $maxEvents;

        if ($hasMoreActivities) {
            $activities = $activities->slice(1, $maxEvents);
        }

        return (new MailMessage)
            ->subject($this->getEmailSubject())
            ->markdown($this->getView(), [
                'notification' => $this,
                'notificationType' => $this->notificationType,
                'ticket' => $this->ticket,
                'actionUrl' => $urlService->getActionUrl($this->ticket),
                'activities' => $activities,
                'hasMoreActivities' => $hasMoreActivities,
                'maxEvents' => $maxEvents,
            ]);
    }

    public function getView(): string
    {
        return 'padmission-tickets::mails.ticket-history';
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
