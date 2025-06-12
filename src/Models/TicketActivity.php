<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketActivityFactory;
use Padmission\Tickets\Events\TicketActivity as TicketActivityEvent;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketClosed;
use Padmission\Tickets\TicketPlugin;

/**
 * @property string $side
 */
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

    public static function booted() : void {
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
}
