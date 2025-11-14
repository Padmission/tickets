<?php

namespace Padmission\Tickets\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketFactory;
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

    public function parentTicket(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            Ticket::class,
            'parentTicket',
            'linked_ticket_id',
            'id'
        );
    }

    public function childTickets(): Relations\PanelAwareHasMany
    {
        return $this->panelAwareHasMany(
            Ticket::class,
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

    public function isInCurrentPanel(): bool
    {
        return $this->panel === Filament::getCurrentOrDefaultPanel()->getId();
    }

    public function isNotInCurrentPanel(): bool
    {
        return $this->panel !== Filament::getCurrentOrDefaultPanel()->getId();
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }
}
