<?php

namespace Padmission\Tickets\Notifications;

use ArrayObject;
use Carbon\CarbonInterval;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
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
         * likewise always deserves a distinct email, even when a debounced
         * activity notification already consumed the closing activity. A
         * hand over changes who the other team's replies go to, which both
         * people must hear about whatever they have read. Whoever just
         * escalated needs no "New ticket" email about their own escalation.
         */
        if ($this->notificationType === 'created') {
            return ! $this->isOwnEscalation($notifiable);
        }

        // Nobody is emailed about what they did themselves.
        if (in_array($this->notificationType, ['closed', 'assigned'], true) && $this->isActor($notifiable)) {
            return false;
        }

        if ($this->notificationType === 'closed') {
            return true;
        }

        if ($this->notificationType === 'handedover') {
            return $this->decideHandOver($notifiable);
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

    protected function isActor($notifiable): bool
    {
        $actor = $this->event->actor ?? null;

        return $actor !== null && (string) $actor->getAuthIdentifier() === (string) $notifiable->getKey();
    }

    public function toMail($notifiable): MailMessage
    {
        $wording = $this->wording($notifiable);

        $message = match ($this->notificationType) {
            'created' => $this->createdMail($wording),
            'closed' => $this->closedMail($wording),
            default => $this->historyMail($notifiable, $wording),
        };

        return $message->subject($wording['subject']);
    }

    /**
     * @param  array<string, string|null>  $wording
     */
    protected function historyMail($notifiable, array $wording): MailMessage
    {
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

        $activities = $this->reportedActivities($notifiable, $activities);

        if ($this->notificationType === 'handedover') {
            $activities = $this->handOverActivities($activities);
        }

        return (new MailMessage)
            ->markdown($this->getView(), [
                ...$wording,
                'notification' => $this,
                'notificationType' => $this->notificationType,
                'ticket' => $this->ticket,
                'activities' => $activities,
                'hasMoreActivities' => $hasMoreActivities,
                'maxEvents' => $maxEvents,
            ]);
    }

    /**
     * @param  array<string, string|null>  $wording
     */
    protected function createdMail(array $wording): MailMessage
    {
        return (new MailMessage)
            ->markdown('padmission-tickets::mails.ticket-created', [
                ...$wording,
                'notification' => $this,
                'ticket' => $this->ticket,
                'assigneeName' => $this->assigneeName(),
            ]);
    }

    /**
     * @param  array<string, string|null>  $wording
     */
    protected function closedMail(array $wording): MailMessage
    {
        $dispositionName = TicketPlugin::resolveModelClass(TicketDisposition::class)::query()
            ->withoutGlobalScopes()
            ->find($this->ticket->disposition_id)
            ?->display_name;

        $lastSupporterMessage = $this->ticket->ticketActivities()
            ->where('type', ActivityType::Message)
            ->where('sender', ActivitySender::Supporter)
            ->latest('id')
            ->first();

        return (new MailMessage)
            ->markdown('padmission-tickets::mails.ticket-closed', [
                ...$wording,
                'notification' => $this,
                'ticket' => $this->ticket,
                'dispositionName' => $dispositionName,
                'lastSupporterMessage' => $lastSupporterMessage?->plainTextContent(),
            ]);
    }

    public function toDatabase($notifiable): array
    {
        $wording = $this->wording($notifiable);

        $activities = $this->getUnreadActivities($notifiable);

        $this->markActivitiesAsSent($notifiable, $activities);

        $body = match ($this->notificationType) {
            'handedover' => $wording['intro'],
            'created' => $this->openingActivities($notifiable, $activities)->last()?->plainTextContent(30) ?? $wording['intro'],
            default => $this->reportedActivities($notifiable, $activities)->last()?->plainTextContent(30) ?? $wording['intro'],
        };

        return FilamentNotification::make()
            ->title($wording['subject'])
            ->body($body)
            ->actions($wording['actionUrl'] === null ? [] : [
                Action::make('view')
                    ->label($wording['actionLabel'])
                    ->url($wording['actionUrl'])
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * What one recipient reads, the same in the email and the bell.
     *
     * @return array{subject: string, headline: string, intro: string, actionLabel: string, actionUrl: string|null, latestReplyLabel?: string, supporterLabel?: string|null}
     */
    protected function wording($notifiable): array
    {
        $key = "padmission-tickets::notifications.ticket-{$this->notificationType}";

        $wording = [
            'subject' => $this->subjectLine("{$key}.subject"),
            'headline' => __("{$key}.headline"),
            'intro' => __($this->notificationType === 'created' && $this->isSubmitter($notifiable) ? "{$key}.intro_requester" : "{$key}.intro"),
            'actionLabel' => __('padmission-tickets::notifications.general.action'),
            'actionUrl' => resolve(TicketUrlService::class)->getActionUrlFor($this->ticket, $notifiable),
        ];

        // The other team's people may be out of reach where this is built, so they go by their team's name.
        if ($this->ticket->isEscalation() && ($this->isSubmitter($notifiable) || $this->notificationType === 'handedover')) {
            $wording['supporterLabel'] = $this->escalationTeamName();
        }

        if ($this->notificationType === 'handedover') {
            return [...$wording, ...$this->handedOverWording($notifiable)];
        }

        if (! $this->isOwnEscalation($notifiable)) {
            return $wording;
        }

        return match ($this->notificationType) {
            'activity' => [...$wording, ...$this->escalationReplyWording($key)],
            'closed' => [...$wording, ...$this->escalationClosedWording($key)],
            default => $wording,
        };
    }

    /**
     * @return array<string, string|null>
     */
    protected function escalationReplyWording(string $key): array
    {
        $team = $this->escalationTeamName();

        return [
            'subject' => TicketPlugin::teamText("{$key}.subject_escalation", $team, $this->subjectReplacements()),
            'headline' => __("{$key}.headline_escalation"),
            'intro' => TicketPlugin::teamText("{$key}.intro_escalation", $team, ['originals' => EscalationSummary::forEscalation($this->ticket)]),
            'actionLabel' => __('padmission-tickets::notifications.general.action_escalation'),
        ];
    }

    /*
     * The requesters are still waiting on the originals, so the email says
     * how many are open and, when one is, opens it with the escalation beside it.
     *
     * @return array<string, string>
     */
    protected function escalationClosedWording(string $key): array
    {
        $team = $this->escalationTeamName();
        $originals = EscalationSummary::originalsOf($this->ticket);
        $open = $originals->whereNull('closed_at')->values();
        $replace = ['originals' => EscalationSummary::originals($originals)];

        $name = $open->count() === 1 ? $open->first()->requesterName() : null;

        $wording = [
            'subject' => TicketPlugin::teamText("{$key}.subject_escalation", $team, $this->subjectReplacements()),
            'headline' => __("{$key}.headline_escalation"),
            'intro' => match (true) {
                $open->isEmpty() => TicketPlugin::teamText("{$key}.intro_escalation", $team, $replace),
                $open->count() === 1 && blank($name) => TicketPlugin::teamText("{$key}.intro_escalation_open_unnamed", $team, $replace),
                default => trans_choice($team === null ? "{$key}.intro_escalation_open" : "{$key}.intro_escalation_open_to", $open->count(), [
                    ...$replace,
                    ...($team === null ? [] : ['team' => $team]),
                    'name' => (string) $name,
                    'count' => $open->count(),
                ]),
            },
            'actionLabel' => __('padmission-tickets::notifications.general.action_escalation'),
            'latestReplyLabel' => TicketPlugin::teamText("{$key}.latest_reply", $team),
        ];

        $originalUrl = $open->count() === 1 ? resolve(TicketUrlService::class)->originalUrl($open->first(), $this->ticket) : null;

        if ($originalUrl === null) {
            return $wording;
        }

        return [
            ...$wording,
            'actionLabel' => $this->openOriginalLabel($open->first()),
            'actionUrl' => $originalUrl,
        ];
    }

    /*
     * Whoever no longer holds the escalation can no longer open it, so their
     * link goes to a ticket they still answer, or to their team's escalations.
     *
     * @return array<string, string|null>
     */
    protected function handedOverWording($notifiable): array
    {
        $key = 'padmission-tickets::notifications.ticket-handedover';
        $team = $this->escalationTeamName();
        $handedToThem = $this->isSubmitter($notifiable);
        $actor = $this->handOverActorName($handedToThem);
        $replace = [
            ...($actor === null ? [] : ['actor' => $actor]),
            'originals' => EscalationSummary::forEscalation($this->ticket),
        ];
        $intro = ($handedToThem ? "{$key}.intro" : "{$key}.intro_taken").($actor === null ? '_unnamed' : '');

        if ($handedToThem) {
            return [
                'intro' => TicketPlugin::teamText($intro, $team, $replace),
                'actionLabel' => __('padmission-tickets::notifications.general.action_escalation'),
            ];
        }

        $original = EscalationSummary::originalsOf($this->ticket)->whereNull('closed_at')->first();
        $url = resolve(TicketUrlService::class)->unviewableEscalationUrl($this->ticket);

        return [
            'subject' => $this->subjectLine("{$key}.subject_taken"),
            'headline' => __("{$key}.headline_taken"),
            'intro' => TicketPlugin::teamText($intro, $team, $replace),
            'actionLabel' => $original !== null
                ? $this->openOriginalLabel($original)
                : __('padmission-tickets::notifications.general.action_escalations'),
            'actionUrl' => $url,
        ];
    }

    /*
     * Without an actor, the person who moved it is the one it left for a
     * take over, and the one it came from for a hand over.
     */
    protected function handOverActorName(bool $handedToThem): ?string
    {
        $actor = $this->event->actor ?? TicketPlugin::resolveUserModelClass()::query()
            ->withoutGlobalScopes()
            ->find($handedToThem ? $this->event->fromId : $this->event->toId);

        $name = $actor === null ? null : Filament::getUserName($actor);

        return filled($name) ? $name : null;
    }

    protected function openOriginalLabel(Ticket $original): string
    {
        $name = $original->requesterName();

        return filled($name)
            ? __('padmission-tickets::notifications.general.action_original', ['name' => $name])
            : __('padmission-tickets::notifications.general.action_original_unnamed');
    }

    /*
     * The team this escalation went to, named where the panel that receives
     * it is registered. Hosts whose workers leave that panel out override it.
     */
    public function escalationTeamName(): ?string
    {
        return TicketPlugin::find($this->ticket->panel)?->getSupportTeamName();
    }

    protected function assigneeName(): ?string
    {
        $assignee = TicketAssignee::for($this->ticket) ?? $this->findUser($this->ticket->assignee_id);

        return $assignee === null ? null : Filament::getUserName($assignee);
    }

    /*
     * Someone named in the ticket's history, found through the ticket's own
     * panel. Hosts whose queue workers leave that panel's scopes in place
     * override it, so another tenant's people are still named.
     */
    protected function findUser(int|string|null $id): ?Model
    {
        if (blank($id)) {
            return null;
        }

        $query = TicketPlugin::resolveUserModelClass()::query();
        $modifier = TicketPlugin::find($this->ticket->panel)?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'user']);
        }

        return $query->find($id);
    }

    /*
     * The history as the email shows it: an assignment names the person, not
     * their number.
     */
    public function activityContent(TicketActivity $activity): string
    {
        $assignee = $activity->type === ActivityType::AssigneeChanged ? $this->findUser($activity->data['to'] ?? null) : null;

        return $assignee !== null
            ? __('padmission-tickets::activities.assigned_to', ['name' => resolve(GetUserDisplayName::class)->forUser($assignee)])
            : (string) $activity->content;
    }

    /*
     * Who wrote a message. When they can't be found here, the side they wrote
     * for is named instead: the other team, or the organization and its contact.
     */
    public function senderName(TicketActivity $activity, ?string $supporterLabel = null): ?string
    {
        if ($activity->sender === ActivitySender::System) {
            return null;
        }

        $writer = $activity->user ?? $this->findUser($activity->user_id);

        if ($writer !== null) {
            return resolve(GetUserDisplayName::class)->forUser($writer);
        }

        if ($activity->sender === ActivitySender::Supporter) {
            return $supporterLabel
                ?? ($this->ticket->isEscalation() ? $this->escalationTeamName() : null)
                ?? __('padmission-tickets::notifications.general.sender-support');
        }

        $organization = $this->ticket->isEscalation() ? TicketPlugin::find($this->ticket->panel)?->describeTicketOrigin($this->ticket) : null;

        return $organization ?? $this->ticket->requesterName() ?? __('padmission-tickets::notifications.general.sender-you');
    }

    protected function isSubmitter($notifiable): bool
    {
        return $this->ticket->isSubmittedBy($notifiable);
    }

    protected function isOwnEscalation($notifiable): bool
    {
        return $this->isSubmitter($notifiable) && $this->ticket->isEscalation();
    }

    protected function getUnreadActivities($notifiable): Collection
    {
        return $this->unreadActivities[$notifiable->getKey()] ??= resolve(TicketActivityService::class)->getUnreadActivities(
            $this->ticket,
            $notifiable,
            config('padmission-tickets.notification-max-events', 10),
        );
    }

    /*
     * A hand over shows the unread messages as background, but they are still
     * owed their own notification: a reply that lands while the hand over is
     * pending would otherwise never be sent. A created notification tells of
     * the ticket's opening only, so a reply already written to the recipient,
     * such as the one sent while escalating, keeps its own notification.
     */
    protected function markActivitiesAsSent($notifiable, Collection $activities): void
    {
        if ($this->notificationType === 'handedover') {
            return;
        }

        if ($this->notificationType === 'created') {
            $activities = $this->openingActivities($notifiable, $activities);
        }

        $latestActivity = $activities->last();

        if ($latestActivity) {
            resolve(TicketActivityService::class)->markAsSent($this->ticket, $notifiable, $latestActivity->id);
        }
    }

    /**
     * The unread activities up to the first message someone else wrote to the recipient.
     *
     * @param  Collection<int, TicketActivity>  $activities
     * @return Collection<int, TicketActivity>
     */
    protected function openingActivities($notifiable, Collection $activities): Collection
    {
        return $activities->takeUntil(fn (TicketActivity $activity): bool => $activity->type === ActivityType::Message
            && $activity->sender !== ActivitySender::System
            && (string) $activity->user_id !== (string) $notifiable->getKey());
    }

    /**
     * The activities worth telling this recipient about: not what they did
     * themselves, and not the close of a closed ticket, which the closed
     * notification tells.
     *
     * @param  Collection<int, TicketActivity>  $activities
     * @return Collection<int, TicketActivity>
     */
    protected function reportedActivities($notifiable, Collection $activities): Collection
    {
        $closedStatusId = $this->ticket->isClosed ? (string) $this->ticket->status_id : null;

        return $activities
            ->reject(fn (TicketActivity $activity): bool => (filled($activity->user_id) && (string) $activity->user_id === (string) $notifiable->getKey())
                || ($closedStatusId !== null && $activity->type === ActivityType::Closed)
                || ($closedStatusId !== null && $activity->type === ActivityType::StatusChanged && (string) ($activity->data['to'] ?? '') === $closedStatusId))
            ->values();
    }

    /**
     * The line for this hand over and the unread messages, without the notes
     * of earlier hand overs that were never marked as notified.
     *
     * @param  Collection<int, TicketActivity>  $activities
     * @return Collection<int, TicketActivity>
     */
    protected function handOverActivities(Collection $activities): Collection
    {
        $line = $activities->last(fn (TicketActivity $activity): bool => $activity->type === ActivityType::HandedOver
            && (string) ($activity->data['to'] ?? '') === (string) $this->event->toId);

        return $activities
            ->filter(fn (TicketActivity $activity): bool => $activity->type === ActivityType::Message || $activity === $line)
            ->values();
    }

    public function getView(): string
    {
        return 'padmission-tickets::mails.ticket-history';
    }

    protected function subjectLine(string $key): string
    {
        return __($key, $this->subjectReplacements());
    }

    /**
     * @return array{subject: string, ticket_id: int|string}
     */
    protected function subjectReplacements(): array
    {
        return [
            'subject' => $this->ticket->subject,
            'ticket_id' => $this->ticket->id,
        ];
    }
}
