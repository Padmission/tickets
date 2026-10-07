<?php

namespace Padmission\Tickets\Notifications;

use ArrayObject;
use Carbon\CarbonInterval;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\EscalationSummary;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAssignee;
use Padmission\Tickets\Services\TicketUrlService;
use Padmission\Tickets\TicketPlugin;

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

    /**
     * Whether to send, decided once per notifiable for every channel, since
     * the decision can record that a hand over went untold.
     *
     * @var ArrayObject<int|string, bool>
     */
    protected ArrayObject $decisions;

    /**
     * @var array{note: ?TicketActivity, message: ?TicketActivity}|null
     */
    protected ?array $openedFor = null;

    public function __construct(
        protected Ticket $ticket,
        protected $event,
    ) {
        $this->unreadActivities = new ArrayObject;
        $this->decisions = new ArrayObject;

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
        return $this->decisions[$notifiable->getKey()] ??= $this->decideToSend($notifiable);
    }

    protected function decideToSend($notifiable): bool
    {
        /*
         * A created event is an acknowledgement of the ticket itself, so it
         * must not be gated on unread activity: the ticket may not have any
         * activities yet at creation time (or the notification may run before
         * they are written when dispatched synchronously). A closed event
         * can send a distinct email to configured recipients, even when a debounced
         * activity notification already consumed the closing activity. A
         * hand over changes who the other team's replies go to, which both
         * people must hear about whatever they have read. Whoever just
         * escalated needs no "New ticket" email about their own escalation.
         */
        if ($this->notificationType === 'created') {
            return $this->isSubmitter($notifiable)
                ? ! ($this->ticket->isEscalation() && $this->isActor($notifiable))
                : ! $this->isActor($notifiable);
        }

        // Nobody is emailed about what they did themselves.
        if (in_array($this->notificationType, ['closed', 'assigned', 'reopened'], true) && $this->isActor($notifiable)) {
            return false;
        }

        if (in_array($this->notificationType, ['reopened', 'assigned'], true) && $this->isSubmitter($notifiable)) {
            return false;
        }

        if ($this->notificationType === 'closed') {
            return ! $this->isSubmitter($notifiable)
                || resolve(NotificationRecipientService::class)->getNotificationRecipients($this->event)
                    ->contains(fn ($recipient): bool => (string) $recipient->getKey() === (string) $notifiable->getKey());
        }

        if ($this->notificationType === 'handedover') {
            return $this->decideHandOver($notifiable);
        }

        // An opted-in close email carries the reply, so avoid a second reply notice.
        if ($this->notificationType === 'activity' && $this->closeTellsThem($notifiable)) {
            return false;
        }

        $activities = $this->reportedActivities($notifiable, $this->getUnreadActivities($notifiable));

        // The owner hears of a reply; the other team's own notes, such as closing it, come some other way or not at all.
        if ($this->notificationType === 'activity' && $this->isOwnEscalation($notifiable)) {
            return $activities->contains(fn (TicketActivity $activity): bool => $activity->type === ActivityType::Message
                && $activity->sender === ActivitySender::Supporter);
        }

        return $activities->isNotEmpty();
    }

    /*
     * A later move, which tells them itself or was their own, can overtake a
     * debounced one. Someone never told the escalation was handed to them is
     * not told either that it was taken from them.
     */
    protected function decideHandOver($notifiable): bool
    {
        $handedToThem = (string) $notifiable->getKey() === (string) $this->event->toId;

        if ($this->isSubmitter($notifiable) !== $handedToThem) {
            return false;
        }

        return $handedToThem || $this->wasToldTheyHeldIt($notifiable);
    }

    /*
     * Worked out from the history rather than from which email went first,
     * since both can fall due together: the "handed to you" email went out
     * only if they still held the escalation when it was due.
     */
    protected function wasToldTheyHeldIt($notifiable): bool
    {
        $key = (string) $notifiable->getKey();
        $handOvers = $this->ticket->ticketActivities()->where('type', ActivityType::HandedOver)->orderBy('id')->get();

        $lost = $handOvers->last(fn (TicketActivity $activity): bool => (string) ($activity->data['from'] ?? '') === $key);
        $gained = $lost === null ? null : $handOvers->last(fn (TicketActivity $activity): bool => $activity->id < $lost->id
            && (string) ($activity->data['to'] ?? '') === $key);

        // Held from the start, or taken over by themselves.
        if ($gained === null || (string) $gained->user_id === $key) {
            return true;
        }

        if (resolve(NotificationRecipientService::class)->getUserNotificationStrategy($notifiable) === NotificationStrategy::Immediate) {
            return true;
        }

        $debounce = (int) config('padmission-tickets.notification-debounce', CarbonInterval::minutes(5)->totalSeconds);

        return $gained->created_at->copy()->addSeconds($debounce)->lte($lost->created_at);
    }

    protected function closeTellsThem($notifiable): bool
    {
        if (! $this->ticket->isClosed || ! $this->isSubmitter($notifiable)
            || blank($this->ticket->closed_by)
            || (string) $this->ticket->closed_by === (string) $notifiable->getKey()) {
            return false;
        }

        $actor = TicketPlugin::resolveUserModelClass()::query()->withoutGlobalScopes()->find($this->ticket->closed_by);
        $event = new TicketClosedEvent($this->ticket, $actor instanceof Authenticatable ? $actor : null);

        return resolve(NotificationRecipientService::class)->getNotificationRecipients($event)
            ->contains(fn ($recipient): bool => (string) $recipient->getKey() === (string) $notifiable->getKey());
    }

