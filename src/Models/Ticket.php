<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Concerns\CanBeAssigned;
use Padmission\Tickets\Models\Concerns\CanBeClosed;
use Padmission\Tickets\Models\Concerns\HasPanelAwareRelationships;
use Padmission\Tickets\Models\Concerns\HasTicketActivities;
use Padmission\Tickets\Models\Concerns\HasTicketAttachments;
use Padmission\Tickets\Models\Concerns\InteractsWithNotifications;
use Padmission\Tickets\Models\Concerns\ManagesPriority;
use Padmission\Tickets\Models\Concerns\ManagesStatus;
use Padmission\Tickets\Models\Observers\TicketObserver;
use Padmission\Tickets\Support\ConversationStateQuery;
use Padmission\Tickets\Support\ConversationViewer;
use Padmission\Tickets\Support\OverdueTicketsQuery;
use Padmission\Tickets\Support\TicketOrganization;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\ValueObjects\SubmitterData;

/**
 * @mixin Model
 */
#[ObservedBy(TicketObserver::class)]
class Ticket extends Model
{
    use CanBeAssigned;
    use CanBeClosed;
    use HasFactory;
    use HasPanelAwareRelationships;
    use HasTicketActivities;
    use HasTicketAttachments;
    use InteractsWithNotifications;
    use ManagesPriority;
    use ManagesStatus;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'turn' => Turn::class,
        'submitter_data' => SubmitterData::class,
        'closed_at' => 'datetime',
    ];

    protected static string $factory = TicketFactory::class;

    protected ?bool $isEscalation = null;

    protected ?bool $isDirectQuestion = null;

    /*
     * The history notes that make a ticket an escalation after its originals
     * are gone, or before it ever had one.
     */
    protected const array ESCALATION_MARKERS = [ActivityType::OriginalAdded, ActivityType::AskedDirectly];

    /*
     * Tickets opened from the chat widget were stored with their subject
     * HTML-escaped, so it is read back as the plain text that was typed.
     * Every place that shows it escapes it again for its own output.
     *
     * @return Attribute<?string, never>
     */
    protected function subject(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => $value === null ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function parentTicket(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(Ticket::class),
            'parentTicket',
            'linked_ticket_id',
            'id'
        );
    }

    public function childTickets(): Relations\PanelAwareHasMany
    {
        return $this->panelAwareHasMany(
            TicketPlugin::resolveModelClass(Ticket::class),
            'childTickets',
            'linked_ticket_id',
            'id'
        );
    }

    /** @return Relations\PanelAwareBelongsTo<Ticket, $this> */
    public function duplicateOriginal(): Relations\PanelAwareBelongsTo
    {
        $relation = $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(Ticket::class),
            'duplicateOriginal',
            'duplicate_of_ticket_id',
        );

        $this->scopeDuplicateRelation($relation->getQuery());

        return $relation;
    }

    /** @return Relations\PanelAwareHasMany<Ticket, $this> */
    public function duplicates(): Relations\PanelAwareHasMany
    {
        $relation = $this->panelAwareHasMany(
            TicketPlugin::resolveModelClass(Ticket::class),
            'duplicates',
            'duplicate_of_ticket_id',
        );

        $this->scopeDuplicateRelation($relation->getQuery());

        return $relation;
    }

    /** @param Builder<Ticket> $query */
    protected function scopeDuplicateRelation(Builder $query): void
    {
        // Eager loading builds the relation on a new model; its normal host
        // scopes apply there. Lazy loading also follows this ticket's scope.
        if (! $this->exists) {
            return;
        }

        $query->where($query->getModel()->qualifyColumn('panel'), $this->panel);

        if (TicketOrganization::shouldScope($this)) {
            $query->where($query->getModel()->qualifyColumn('tenant_id'), $this->getAttribute('tenant_id'));
        }
    }

    /* Scopes */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return OverdueTicketsQuery::apply($query);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closed_at');
    }

    public function scopeWithConversationState(Builder $query, ?ConversationViewer $viewer = null): Builder
    {
        return ConversationStateQuery::apply($query, $viewer ?? ConversationViewer::current());
    }

    public function scopeEscalations(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $this->whereEscalation($query));
    }

    public function scopeDirectQuestions(Builder $query): Builder
    {
        $id = $query->qualifyColumn($this->getKeyName());
        $activities = TicketPlugin::resolveModelClass(TicketActivity::class)::query()->withoutGlobalScopes();

        return $query->where(fn (Builder $query): Builder => $query
            ->whereNotExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                ->selectRaw('1')
                ->from($this->getTable(), 'direct_originals')
                ->whereNull('direct_originals.'.$this->getDeletedAtColumn())
                ->whereColumn('direct_originals.linked_ticket_id', $id))
            ->whereExists((clone $activities)->whereColumn('ticket_id', $id)->where('type', ActivityType::AskedDirectly)->selectRaw('1'))
            ->whereNotExists((clone $activities)->whereColumn('ticket_id', $id)->where('type', ActivityType::OriginalAdded)->selectRaw('1')));
    }

    public function scopeWithoutEscalations(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $query): Builder => $this->whereEscalation($query));
    }

    /*
     * Twin of isEscalationFrom(). The originals are read without any scope, as
     * there, so a host's tenant scope never hides another tenant's originals.
     */
    public function scopeEscalationsFrom(Builder $query, string $panelId): Builder
    {
        $id = $query->qualifyColumn($this->getKeyName());

        return $query
            ->whereIn($query->qualifyColumn('panel'), array_keys(TicketPlugin::find($panelId)?->getLinkedTicketParentPanels() ?? []))
            ->where(fn (Builder $query): Builder => $query
                ->whereExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                    ->selectRaw('1')
                    ->from($this->getTable(), 'panel_originals')
                    ->whereNull('panel_originals.'.$this->getDeletedAtColumn())
                    ->whereColumn('panel_originals.linked_ticket_id', $id)
                    ->where('panel_originals.panel', $panelId))
                ->orWhere(fn (Builder $query): Builder => $this->whereEscalation($query
                    ->where($query->qualifyColumn('source_panel'), $panelId))));
    }

    /*
     * Twin of isEscalation(). Comparing panel with source_panel cannot tell,
     * because widget tickets filed into a target panel differ too.
     */
    protected function whereEscalation(Builder $query): Builder
    {
        $id = $query->qualifyColumn($this->getKeyName());
        $activities = (new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable();

        return $query->where(fn (Builder $query): Builder => $query
            ->whereExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                ->selectRaw('1')
                ->from($this->getTable(), 'escalation_originals')
                ->whereNull('escalation_originals.'.$this->getDeletedAtColumn())
                ->whereColumn('escalation_originals.linked_ticket_id', $id))
            ->orWhereExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                ->selectRaw('1')
                ->from($activities, 'escalation_activities')
                ->whereColumn('escalation_activities.ticket_id', $id)
                ->whereIn('escalation_activities.type', array_map(fn (ActivityType $type): string => $type->value, static::ESCALATION_MARKERS))));
    }

    /*
     * Closing leaves the turn as it was, so reopening picks up where the
     * conversation stopped, but a closed ticket is not waiting on anyone.
     */
    public function waitingOn(): ?Turn
    {
        return $this->isClosed ? null : $this->turn;
    }

    /*
     * An escalation keeps this identity after all its originals are removed,
     * through the history note written when the first one was added.
     */
    public function isEscalation(): bool
    {
        if (! $this->exists) {
            return false;
        }

        // A list row that already loaded its originals needs no query per row.
        if ($this->isEscalation === null && $this->relationLoaded('childTickets') && $this->childTickets->contains(fn (Ticket $original): bool => ! $original->trashed())) {
            return $this->isEscalation = true;
        }

        if ($this->isEscalation === null && array_key_exists('conversation_is_escalation', $this->attributes)) {
            return $this->isEscalation = (bool) $this->attributes['conversation_is_escalation'];
        }

        return $this->isEscalation ??= $this->activeOriginalsQuery()->exists()
            || TicketPlugin::resolveModelClass(TicketActivity::class)::query()
                ->withoutGlobalScopes()
                ->where('ticket_id', $this->getKey())
                ->whereIn('type', static::ESCALATION_MARKERS)
                ->exists();
    }

    /*
     * An escalation asked straight of the other team, which no original ticket
     * has ever joined. Once one is added it is an escalation like any other,
     * even after that original is removed again.
     */
    public function isDirectQuestion(): bool
    {
        if ($this->isDirectQuestion !== null) {
            return $this->isDirectQuestion;
        }

        if (array_key_exists('conversation_is_direct_question', $this->attributes)) {
            return $this->isDirectQuestion = (bool) $this->attributes['conversation_is_direct_question'];
        }

        if (! $this->isEscalation() || $this->activeOriginalsQuery()->exists()) {
            return $this->isDirectQuestion = false;
        }

        $markers = TicketPlugin::resolveModelClass(TicketActivity::class)::query()
            ->withoutGlobalScopes()
            ->where('ticket_id', $this->getKey())
            ->whereIn('type', static::ESCALATION_MARKERS)
            ->distinct()
            ->pluck('type');

        return $this->isDirectQuestion = $markers->contains(ActivityType::AskedDirectly) && ! $markers->contains(ActivityType::OriginalAdded);
    }

    /**
     * Ignore host scopes, as the SQL scopes do, but never count deleted originals.
     *
     * @return Builder<Ticket>
     */
    protected function activeOriginalsQuery(): Builder
    {
        return $this->newQueryWithoutScopes()
            ->whereNull($this->getQualifiedDeletedAtColumn())
            ->where('linked_ticket_id', $this->getKey());
    }

    public function forgetIsEscalation(): void
    {
        $this->isEscalation = null;
        $this->isDirectQuestion = null;
    }

    public function refresh()
    {
        $this->forgetIsEscalation();

        return parent::refresh();
    }

    /*
     * The observer clears duplicate links after the row is deleted, so a
     * cleanup failure would otherwise leave this ticket deleted behind
     * dangling links, hidden from the list that could retry the delete.
     * Per-record, so each stays atomic before a bulk action catches it.
     */
    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }

    public function isEscalationFrom(string $panelId): bool
    {
        if (! $this->isEscalation()) {
            return false;
        }

        if (! array_key_exists($this->panel, TicketPlugin::find($panelId)?->getLinkedTicketParentPanels() ?? [])) {
            return false;
        }

        return $this->source_panel === $panelId
            || $this->activeOriginalsQuery()
                ->where('panel', $panelId)
                ->exists();
    }

    /*
     * The panel whose team talks with the other team on this escalation. An
     * escalation made before source_panel was recorded falls back to where
     * its first original lives.
     */
    public function escalationSourcePanel(): ?string
    {
        if (filled($this->source_panel)) {
            return $this->source_panel;
        }

        return $this->activeOriginalsQuery()
            ->orderBy('id')
            ->value('panel');
    }

    /*
     * Ids compare as strings, since a driver may read the same id back as a
     * string in one place and an integer in another.
     */
    public function isSubmittedBy(Model|Authenticatable|int|string|null $user): bool
    {
        $id = match (true) {
            $user instanceof Model => $user->getKey(),
            $user instanceof Authenticatable => $user->getAuthIdentifier(),
            default => $user,
        };

        return filled($id) && filled($this->submitter_id) && (string) $id === (string) $this->submitter_id;
    }

    public function requesterName(): ?string
    {
        return $this->submitter !== null
            ? Filament::getUserName($this->submitter)
            : $this->submitter_data?->name;
    }

    public function isInCurrentPanel(): bool
    {
        return $this->panel === Filament::getCurrentOrDefaultPanel()->getId();
    }

    public function isNotInCurrentPanel(): bool
    {
        return $this->panel !== Filament::getCurrentOrDefaultPanel()->getId();
    }
}
