<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Observers\TicketObserver;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\ValueObjects\SubmitterData;

#[UseFactory(TicketFactory::class)]
#[ObservedBy(TicketObserver::class)]
class Ticket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'turn' => Turn::class,
        'submitter_data' => SubmitterData::class,
        'closed_at' => 'datetime',
    ];

    /* Relations */

    /**
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Status::class)
        )->withTrashed();
    }

    /**
     * @return BelongsTo<Priority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Priority::class)
        )->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'submitter_id'
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'assignee_id'
        );
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketPlugin::resolveModelClass(Activity::class), 'ticket_id');
    }

    public function latestActivity(): HasOne
    {
        return $this
            ->hasOne(TicketPlugin::resolveModelClass(Activity::class), 'ticket_id')
            ->latestOfMany();
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

    /* Attributes */

    /**
     * @return Attribute<bool, never>
     */
    protected function isClosed(): Attribute
    {
        return Attribute::get(fn () => $this->closed_at !== null);
    }

    /* Business Logic */
    public function close(?string $disposition = null, ?int $closedBy = null): void
    {
        if ($this->isClosed) {
            return;
        }

        $statusModel = TicketPlugin::resolveModelClass(Status::class);
        $closedStatus = $statusModel::query()->orderBy('order', 'DESC')->first();

        DB::beginTransaction();

        $this->activities()->create([
            'type' => ActivityType::Closed,
            'sender' => ActivitySender::System,
            'data' => [
                'closed_by' => $closedBy,
            ],
        ]);

        $this->update([
            'status_id' => $closedStatus->getKey(),
            'disposition' => $disposition,
            'closed_at' => now(),
            'closed_by' => $closedBy,
        ]);

        DB::commit();
    }
}
