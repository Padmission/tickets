<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
#[UseFactory(TicketFactory::class)]
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

    public function parentTicket(): BelongsTo
    {
        return $this->panelAwareBelongsTo(
            Ticket::class,
            'parentTicket',
            'linked_ticket_id',
            'id'
        );
    }

    public function childTickets(): HasMany
    {
        return $this->panelAwareHasMany(
            Ticket::class,
            'childTickets',
            'linked_ticket_id',
            'id'
        );
    }

    /* Scopes */

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    #[Scope]
    protected function closed(Builder $query): void
    {
        $query->whereNotNull('closed_at');
    }
}
