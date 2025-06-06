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
     * @return BelongsTo<TicketStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketStatus::class)
        )->withTrashed();
    }

    /**
     * @return BelongsTo<TicketPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketPriority::class)
        )->withTrashed();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'submitter_id'
        );
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'assignee_id'
        );
    }

    /**
     * @return HasMany<TicketActivity, $this>
     */
    public function ticketActivities(): HasMany
    {
        return $this->hasMany(TicketPlugin::resolveModelClass(TicketActivity::class), 'ticket_id');
    }

    /**
     * @return HasOne<TicketActivity, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this
            ->hasOne(TicketPlugin::resolveModelClass(TicketActivity::class), 'ticket_id')
            ->ofMany([
                'created_at' => 'max',
            ], function (Builder $query) {
                $query->where('type', ActivityType::Message->value);
            });

    }

    /**
     * @return HasOne<TicketActivity, $this>
     */
    public function latestActivity(): HasOne
    {
        return $this
            ->hasOne(TicketPlugin::resolveModelClass(TicketActivity::class), 'ticket_id')
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
    public function close(?int $closedBy = null): void
    {
        if ($this->isClosed) {
            return;
        }

        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);
        $closedStatus = $statusModel::query()->orderBy('order', 'DESC')->first();

        DB::beginTransaction();

        $this->ticketActivities()->create([
            'type' => ActivityType::Closed,
            'sender' => ActivitySender::System,
            'data' => [
                'closed_by' => $closedBy,
            ],
        ]);

        $this->update([
            'status_id' => $closedStatus->getKey(),
            'closed_at' => now(),
            'closed_by' => $closedBy,
        ]);

        DB::commit();
    }
}
