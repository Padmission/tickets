<?php

namespace Padmission\Tickets\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Contracts\Auth\Authenticatable;
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
use Padmission\Tickets\TicketPlugin;

/**
 * @property ActivitySide $side
 */
#[UseFactory(TicketActivityFactory::class)]
class TicketActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ticket_activities';

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'type' => ActivityType::class,
        'sender' => ActivitySender::class,
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->user_id ??= auth()->user()?->id;
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
    protected function userName(): Attribute
    {
        return Attribute::get(function () {
            if ($this->side === ActivitySide::Me) {
                return __('padmission-tickets::tickets.side_you');
            }

            $user = $this->user;

            if (method_exists($user, 'getSupportName')) {
                return $user->getSupportName();
            }

            if ($user instanceof HasName) {
                return $user->getFilamentName();
            }

            if (isset($user->name)) {
                return $user->name;
            }

            return '';
        });
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
