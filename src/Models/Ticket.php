<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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

    /* Scopes */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closed_at');
    }

    public function scopeEscalations(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $this->whereEscalation($query));
    }

    public function scopeWithoutEscalations(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $query): Builder => $this->whereEscalation($query));
    }

    /*
     * Twin of isEscalation(). Comparing panel with source_panel cannot tell,
     * because widget tickets filed into a target panel differ too.
     */
    protected function whereEscalation(Builder $query): Builder
    {
        $id = $query->qualifyColumn($this->getKeyName());
        $activities = (new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable();

        return $query
            ->whereExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                ->selectRaw('1')
                ->from($this->getTable(), 'escalation_originals')
                ->whereColumn('escalation_originals.linked_ticket_id', $id))
            ->orWhereExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                ->selectRaw('1')
                ->from($activities, 'escalation_activities')
                ->whereColumn('escalation_activities.ticket_id', $id)
                ->where('escalation_activities.type', ActivityType::OriginalAdded->value));
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

        return $this->isEscalation ??= $this->newQueryWithoutScopes()->where('linked_ticket_id', $this->getKey())->exists()
            || TicketPlugin::resolveModelClass(TicketActivity::class)::query()
                ->withoutGlobalScopes()
                ->where('ticket_id', $this->getKey())
                ->where('type', ActivityType::OriginalAdded)
                ->exists();
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
            || $this->newQueryWithoutScopes()
                ->where('linked_ticket_id', $this->getKey())
                ->where('panel', $panelId)
                ->exists();
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
