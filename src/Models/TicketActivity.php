<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketActivityFactory;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Events\TicketActivity as TicketActivityEvent;
use Padmission\Tickets\Models\Observers\TicketActivityObserver;
use Padmission\Tickets\TicketPlugin;

/**
 * @property ActivitySide $side
 */

#[ObservedBy(TicketActivityObserver::class)]
#[UseFactory(TicketActivityFactory::class)]
class TicketActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ticket_activities';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'type' => ActivityType::class,
        'sender' => ActivitySender::class,
        'created_at' => 'immutable_datetime',
    ];

    public static function booted(): void
    {
        static::saved(function (TicketActivity $activity) {
            event(new TicketActivityEvent($activity->ticket, $activity->type->value, null));
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Ticket::class)
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class)
        );
    }

    /**
     * @return Attribute<string,never>
     */
    protected function content(): Attribute
    {
        // TODO: Cache status, priority and make the notifications configurable

        return Attribute::get(fn ($value) => match ($this->type) {
            ActivityType::Opened => __('padmission-tickets::activities.opened'),
            ActivityType::Closed => __('padmission-tickets::activities.closed'),
            ActivityType::TurnChanged => __('padmission-tickets::activities.turn_changed', [
                'from' => Turn::tryFrom($this->data['from'])->getLabel(),
                'to' => Turn::tryFrom($this->data['to'])->getLabel(),
            ]),
            ActivityType::StatusChanged => __('padmission-tickets::activities.status_changed', [
                'from' => TicketStatus::find($this->data['from'])->display_name,
                'to' => TicketStatus::find($this->data['to'])->display_name,
            ]),
            ActivityType::PriorityChanged => __('padmission-tickets::activities.priority_changed', [
                'from' => TicketPriority::find($this->data['from'])->display_name,
                'to' => TicketPriority::find($this->data['to'])->display_name,
            ]),
            default => $value
        });
    }
}
