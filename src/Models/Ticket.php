<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Concerns\CanBeAssigned;
use Padmission\Tickets\Models\Concerns\CanBeClosed;
use Padmission\Tickets\Models\Concerns\HasTicketActivities;
use Padmission\Tickets\Models\Concerns\InteractsWithNotifications;
use Padmission\Tickets\Models\Concerns\ManagesPriority;
use Padmission\Tickets\Models\Concerns\ManagesStatus;
use Padmission\Tickets\Models\Observers\TicketObserver;
use Padmission\Tickets\ValueObjects\SubmitterData;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
#[UseFactory(TicketFactory::class)]
#[ObservedBy(TicketObserver::class)]
class Ticket extends Model
{
    use CanBeAssigned;
    use CanBeClosed;
    use HasFactory;
    use HasTicketActivities;
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

    /* Scopes */

    protected function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    protected function scopeClosed(Builder $query): void
    {
        $query->whereNotNull('closed_at');
    }

    /* Attributes */

    /**
     * @return Attribute<bool, never>
     */
    protected function isClosed(): Attribute
    {
        return Attribute::get(fn () => $this->closed_at !== null);
    }
}
