<?php

namespace Padmission\Tickets\Models;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Database\Factories\TicketActivityFactory;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Concerns\HasPanelAwareRelationships;
use Padmission\Tickets\Models\Concerns\HasTicketAttachments;
use Padmission\Tickets\Models\Observers\TicketActivityObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Services\TicketUrlService;
use Padmission\Tickets\TicketPlugin;

/**
 * @property ActivitySide $side
 * @property bool $isOwn
 */
#[ObservedBy(TicketActivityObserver::class)]
class TicketActivity extends Model
{
    use HasFactory;
    use HasPanelAwareRelationships;
    use HasTicketAttachments;

    protected $table = 'ticket_activities';

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'type' => ActivityType::class,
        'sender' => ActivitySender::class,
        'turn' => Turn::class,
        'created_at' => 'immutable_datetime',
    ];

    protected static string $factory = TicketActivityFactory::class;

    /**
     * @return Relations\PanelAwareBelongsTo<Ticket,$this>
     */
    public function ticket(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(Ticket::class),
            'ticket'
        );
    }

    /**
     * @return Relations\PanelAwareBelongsTo<Model&Authenticatable, $this>
     */
    public function user(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveUserModelClass(),
            'user'
        );
    }

    public function plainTextContent(?int $words = null): ?string
    {
        return static::plainText($this->content, $words);
    }

    public static function plainText(?string $content, ?int $words = null): ?string
    {
        if ($content === null) {
            return null;
        }

        $spacedContent = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|h[1-6]|blockquote|pre|tr)>/i', ' ', (string) $content) ?? (string) $content;
        $plainText = Str::squish(html_entity_decode(strip_tags($spacedContent), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($plainText === '') {
            return null;
        }

        return $words === null ? $plainText : Str::words($plainText, $words);
    }

    /**
     * @return Attribute<string,never>
     */
    protected function userName(): Attribute
    {
        return Attribute::get(function () {
            if ($this->isOwn === true) {
                return __('padmission-tickets::tickets.side_you');
            }

            return $this->actorName();
        });
    }

    /**
     * The content is HTML, as the chat and the ticket page render it, so
     * every name and label a note quotes is escaped here.
     *
     * @return Attribute<string,never>
     */
    protected function content(): Attribute
    {
        // TODO: Cache status, priority and make the notifications configurable

        return Attribute::get(fn ($value) => match ($this->type) {
            ActivityType::Opened => __('padmission-tickets::activities.opened'),
            ActivityType::Closed => __('padmission-tickets::activities.closed'),
            ActivityType::Reopened => blank($this->user_id)
                ? __('padmission-tickets::activities.reopened_unknown')
                : __('padmission-tickets::activities.reopened', ['name' => e($this->actorName())]),
            ActivityType::AssigneeChanged => $this->assigneeNote(auth()->id()),
            ActivityType::TurnChanged => __('padmission-tickets::activities.turn_changed', [
                'from' => $this->turnLabel($this->activityData('from')),
                'to' => $this->turnLabel($this->activityData('to')),
            ]),
            ActivityType::StatusChanged => __('padmission-tickets::activities.status_changed', [
                'from' => e($this->statusLabel($this->activityData('from'))),
                'to' => e($this->statusLabel($this->activityData('to'))),
            ]),
            ActivityType::PriorityChanged => __('padmission-tickets::activities.priority_changed', [
                'from' => e($this->priorityLabel($this->activityData('from'))),
                'to' => e($this->priorityLabel($this->activityData('to'))),
            ]),
            ActivityType::Escalated => $this->escalationNote('escalated'),
            ActivityType::AddedToEscalation => $this->escalationNote('added_to_escalation'),
            ActivityType::RemovedFromEscalation => $this->escalationNote('removed_from_escalation'),
            ActivityType::OriginalAdded => $this->originalNote('original_added'),
            ActivityType::OriginalRemoved => $this->originalNote('original_removed'),
            ActivityType::HandedOver => $this->handOverNote(),
            ActivityType::FollowsUp => $this->followsUpNote(),
            ActivityType::OpenedFor => $this->openedForNote(auth()->id()),
            ActivityType::SubjectChanged => __('padmission-tickets::activities.subject_changed', [
                'from' => e($this->subjectText($this->activityData('from'))),
                'to' => e($this->subjectText($this->activityData('to'))),
            ]),
            default => $value
        });
    }

    /*
     * The requester reads that it was opened for them, and the supporter who
     * opened it reads it as their own.
     */
    public function openedForNote(int|string|null $viewerId): string
    {
        $requesterId = $this->activityData('requester');
        $key = 'padmission-tickets::activities.opened_for';

        // The other team opened it for someone on the team that escalates, who reads it by the other team's name.
        $team = $this->activityData('by_team') ? TicketPlugin::find($this->ticketPanelId())?->getSupportTeamName() : null;

        return match (true) {
            filled($viewerId) && (string) $viewerId === (string) $this->user_id => __("{$key}_by_you", ['requester' => e($this->nameOf($requesterId))]),
            (bool) $this->activityData('by_team') => TicketPlugin::teamText(
                filled($viewerId) && (string) $viewerId === (string) $requesterId ? "{$key}_team_you" : "{$key}_team",
                $team === null ? null : e($team),
                ['requester' => e($this->nameOf($requesterId))],
            ),
            filled($viewerId) && (string) $viewerId === (string) $requesterId => __("{$key}_you", ['name' => e($this->actorName())]),
            default => __($key, ['name' => e($this->actorName()), 'requester' => e($this->nameOf($requesterId))]),
        };
    }

    /*
     * The earlier ticket is linked where its reader can open it: a requester
     * in their tickets, a supporter on its page.
     */
    protected function followsUpNote(): string
    {
        $number = '#'.e((string) $this->activityData('ticket'));
        $earlier = TicketPlugin::resolveModelClass(Ticket::class)::query()->withoutGlobalScopes()->find($this->activityData('ticket'));
        $viewer = auth()->user();
        $url = $earlier instanceof Ticket && $viewer instanceof Model ? resolve(TicketUrlService::class)->getActionUrlFor($earlier, $viewer) : null;

        return __('padmission-tickets::activities.follows_up', [
            'ticket' => $url === null ? $number : '<a href="'.e($url).'">'.$number.'</a>',
        ]);
    }

    /**
     * @return Attribute<string,never>
     */
    protected function senderName(): Attribute
    {
        return Attribute::get(fn (): string => $this->actorName());
    }

    protected function actorName(): string
    {
        if ($this->relationLoaded('user') && $this->user !== null) {
            return resolve(GetUserDisplayName::class)->forUser($this->user);
        }

        return $this->nameOf($this->user_id);
    }

    /*
     * Someone this activity names, looked up by the reader's panel and then
     * the ticket's own, whose scopes may reveal another tenant's people, as
     * Padmission staff assigned to an escalation are when the chat reads it
     * through the API route rather than the admin panel. Only the name leaves.
     */
    protected function nameOf(mixed $id): string
    {
        return resolve(GetUserDisplayName::class)(is_numeric($id) ? (int) $id : null, $this->viewerPanelId(), $this->ticketPanelId());
    }

    protected function ticketPanelId(): ?string
    {
        if (! $this->relationLoaded('ticket')) {
            $this->setRelation('ticket', TicketPlugin::resolveModelClass(Ticket::class)::withoutGlobalScopes()->find($this->ticket_id));
        }

        return $this->ticket?->panel;
    }

    /*
     * History notes name the other ticket by its relationship rather than its
     * number, which is a database id and means nothing on its own. The number
     * stays in the link's tooltip for anyone quoting it.
     */
    protected function escalationNote(string $key): string
    {
        $escalation = $this->linkedTicket('escalation');
        $team = $escalation === null ? null : TicketPlugin::find($escalation->panel)?->getSupportTeamName();

        // The ticket that started the escalation went to the team; any other joined the escalation.
        $label = TicketPlugin::teamText($key === 'escalated' ? 'padmission-tickets::activities.escalation_target' : 'padmission-tickets::activities.escalation', $team);

        return __("padmission-tickets::activities.{$key}", [
            'escalation' => $this->ticketReference($escalation, $label, $escalation === null ? null : $this->viewUrlFor($escalation)),
            'name' => e($this->actorName()),
        ]);
    }

    protected function originalNote(string $key): string
    {
        $original = $this->linkedTicket('original');
        $requester = $original?->requesterName();

        $label = filled($requester)
            ? __('padmission-tickets::activities.original_of', ['name' => $requester])
            : __('padmission-tickets::activities.original');

        return __("padmission-tickets::activities.{$key}", [
            'original' => $this->ticketReference($original, $label, $original === null ? null : $this->originalUrl($original)),
            'name' => e($this->actorName()),
        ]);
    }

    /**
     * A ticket moving from one person to another says so and who moved it, so
     * taking it from a colleague never reads like a first assignment. The
     * viewer reads as "You"; everyone else is named by the given lookup.
     *
     * @param  (Closure(mixed): string)|null  $name
     */
    public function assigneeNote(int|string|null $viewerId, ?Closure $name = null): string
    {
        $name ??= fn (mixed $id): string => $this->nameOf($id);
        $from = $this->activityData('from');
        $to = $this->activityData('to');

        if ($this->ticket?->isSubmittedBy($viewerId)) {
            return blank($to)
                ? __('padmission-tickets::activities.unassigned_requester')
                : __('padmission-tickets::activities.assigned_requester', ['name' => e($name($to))]);
        }

        if (blank($to)) {
            return __('padmission-tickets::activities.unassigned');
        }

        if (blank($from)) {
            return __('padmission-tickets::activities.assigned_to', ['name' => e($name($to))]);
        }

        $person = fn (mixed $id, bool $opensSentence): string => filled($viewerId) && (string) $id === (string) $viewerId
            ? __($opensSentence ? 'padmission-tickets::activities.you' : 'padmission-tickets::activities.you_later')
            : e($name($id));

        return match (true) {
            blank($this->user_id) => __('padmission-tickets::activities.reassigned_unknown', [
                'from' => $person($from, false),
                'to' => $person($to, false),
            ]),
            (string) $this->user_id === (string) $to => __('padmission-tickets::activities.assignee_taken', [
                'to' => $person($to, true),
                'from' => $person($from, false),
            ]),
            (string) $this->user_id === (string) $from => __('padmission-tickets::activities.assignee_handed', [
                'from' => $person($from, true),
                'to' => $person($to, false),
            ]),
            default => __('padmission-tickets::activities.reassigned', [
                'name' => $person($this->user_id, true),
                'from' => $person($from, false),
                'to' => $person($to, false),
            ]),
        };
    }

    protected function handOverNote(): string
    {
        $from = $this->activityData('from');
        $to = $this->activityData('to');

        return __(
            (string) $to === (string) $this->user_id ? 'padmission-tickets::activities.taken_over' : 'padmission-tickets::activities.handed_over',
            ['from' => e($this->nameOf($from)), 'to' => e($this->nameOf($to))],
        );
    }

    protected function linkedTicket(string $key): ?Ticket
    {
        $id = $this->activityData($key);

        if (blank($id)) {
            return null;
        }

        // Loaded with the reading panel's scope, as the chat's own senders are, so a
        // panel that may see other organizations' people can name the requester.
        $modifier = TicketPlugin::find($this->viewerPanelId())?->getRelationshipScopeModifier();

        return TicketPlugin::resolveModelClass(Ticket::class)::withoutGlobalScopes()
            ->with(['submitter' => fn (Relation $relation) => $modifier === null ? $relation : app()->call($modifier, ['relation' => $relation, 'model' => 'submitter'])])
            ->find($id);
    }

    /*
     * The team an original was escalated to reads it beside the escalation,
     * never on its own page, where it could write to the requester.
     */
    protected function originalUrl(Ticket $original): ?string
    {
        $escalation = TicketPlugin::resolveModelClass(Ticket::class)::withoutGlobalScopes()->find($this->ticket_id);

        if ($escalation === null || $escalation->panel !== $this->viewerPanelId()) {
            return $this->viewUrlFor($original);
        }

        return $this->viewUrlFor($escalation, ['linked' => $original->getKey()]);
    }

    protected function ticketReference(?Ticket $ticket, string $label, ?string $url): string
    {
        if ($ticket === null || $url === null) {
            return e($label);
        }

        return sprintf(
            '<a href="%s" title="%s">%s</a>',
            e($url),
            e(__('padmission-tickets::activities.ticket_number', ['id' => $ticket->getKey()])),
            e($label),
        );
    }

    /**
     * Only a ticket the viewer could open from the panel they are reading in
     * gets a link. The chat widget reads history outside any panel request, so
     * it names its panel in a header.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function viewUrlFor(Ticket $ticket, array $parameters = []): ?string
    {
        $panelId = $this->viewerPanelId();
        $plugin = TicketPlugin::find($panelId);

        if ($plugin === null || auth()->user()?->can('view', $ticket) !== true) {
            return null;
        }

        if ($plugin->getTicketQuery()->whereKey($ticket->getKey())->doesntExist()) {
            return null;
        }

        return rescue(
            fn (): string => TicketResource::getUrl('view', ['record' => $ticket->getKey(), ...$parameters], panel: $panelId),
            report: false,
        );
    }

    protected function viewerPanelId(): ?string
    {
        return Str::after((string) request()->header('X-Padmission-Tickets-Panel'), 'panel-')
            ?: Filament::getCurrentPanel()?->getId();
    }

    protected function activityData(string $key): mixed
    {
        return data_get($this->data ?? [], $key);
    }

    protected function turnLabel(mixed $value): string
    {
        if (! is_string($value)) {
            return $this->unknownLabel();
        }

        return Turn::tryFrom($value)?->getLabel() ?? $this->unknownLabel();
    }

    protected function statusLabel(mixed $id): string
    {
        return TicketStatus::withoutGlobalScope(CurrentPanelScope::class)
            ->find($id)
            ->display_name ?? $this->unknownLabel();
    }

    protected function priorityLabel(mixed $id): string
    {
        return TicketPriority::withoutGlobalScope(CurrentPanelScope::class)
            ->find($id)
            ->display_name ?? $this->unknownLabel();
    }

    // As the ticket reads its subject: one from the chat widget was stored escaped.
    protected function subjectText(mixed $subject): string
    {
        return is_string($subject) ? html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $this->unknownLabel();
    }

    protected function unknownLabel(): string
    {
        return __('padmission-tickets::activities.unknown');
    }
}
