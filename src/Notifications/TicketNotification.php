<?php

namespace Padmission\Tickets\Notifications;

use ArrayObject;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketUrlService;

class TicketNotification extends Notification
{
    public $notificationType;

    /**
     * Unread activities per notifiable, read once so every channel of one send
     * sees the same batch: the first channel to render marks it as sent.
     * Laravel hands each channel a clone of the notification, and the clones
     * share this object where they would each get their own copy of an array.
     *
     * @var ArrayObject<int|string, Collection>
     */
    protected ArrayObject $unreadActivities;

    public function __construct(
        protected Ticket $ticket,
        protected $event,
    ) {
        $this->unreadActivities = new ArrayObject;

        $this->notificationType = str($this->event::class)
            ->afterLast('\\')
            ->replace('Ticket', '')
            ->replace('Event', '')
            ->lower()
            ->toString();
    }

    public function via($notifiable): array
    {
        return (array) config('padmission-tickets.notification-channels', ['mail']);
    }

    public function shouldSend($notifiable): bool
    {
        /*
         * A created event is an acknowledgement of the ticket itself, so it
         * must not be gated on unread activity: the ticket may not have any
         * activities yet at creation time (or the notification may run before
         * they are written when dispatched synchronously). A closed event
         * likewise always deserves a distinct email, even when a debounced
         * activity notification already consumed the closing activity.
         */
        if (in_array($this->notificationType, ['created', 'closed'], true)) {
            return true;
        }

        return $this->getUnreadActivities($notifiable)->isNotEmpty();
    }

    public function toMail($notifiable): MailMessage
    {
        $urlService = resolve(TicketUrlService::class);

        $maxEvents = config('padmission-tickets.notification-max-events', 10);

        $activities = $this->getUnreadActivities($notifiable);

        $this->markActivitiesAsSent($notifiable, $activities);

        $hasMoreActivities = $activities->count() > $maxEvents;

        /*
         * Intentional: the fetch is newest-first, so on overflow the email renders
         * only the latest $maxEvents activities while markAsSent (above, keyed on
         * the newest id) marks EVERY unread activity as notified — including ones
         * never fetched. Activities older than the rendered window are deliberately
         * never emailed; the "more activities" banner directs the recipient to the
         * site for full history. Do not "fix" by re-slicing or by marking only
         * rendered activities as sent.
         */
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

    public function toDatabase($notifiable): array
    {
        $activities = $this->getUnreadActivities($notifiable);

        $this->markActivitiesAsSent($notifiable, $activities);

        return FilamentNotification::make()
            ->title($this->getEmailSubject())
            ->body(
                $activities->last()?->plainTextContent(30)
                    ?? __("padmission-tickets::notifications.ticket-{$this->notificationType}.intro")
            )
            ->actions([
                Action::make('view')
                    ->label(__('padmission-tickets::notifications.general.action'))
                    ->url(resolve(TicketUrlService::class)->getActionUrl($this->ticket))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    protected function getUnreadActivities($notifiable): Collection
    {
        return $this->unreadActivities[$notifiable->getKey()] ??= resolve(TicketActivityService::class)->getUnreadActivities(
            $this->ticket,
            $notifiable,
            config('padmission-tickets.notification-max-events', 10),
        );
    }

    protected function markActivitiesAsSent($notifiable, Collection $activities): void
    {
        $latestActivity = $activities->last();

        if ($latestActivity) {
            resolve(TicketActivityService::class)->markAsSent($this->ticket, $notifiable, $latestActivity->id);
        }
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
