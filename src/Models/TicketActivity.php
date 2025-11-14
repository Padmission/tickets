<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Database\Factories\TicketActivityFactory;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Concerns\HasPanelAwareRelationships;
use Padmission\Tickets\Models\Concerns\HasTicketAttachments;
use Padmission\Tickets\Models\Observers\TicketActivityObserver;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\TicketPlugin;

/**
 * @property ActivitySide $side
 */
class TicketActivity extends Model
{
    use HasFactory;
    use HasPanelAwareRelationships;
    use HasTicketAttachments;

    protected $table = 'ticket_activities';

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'type' => ActivityType::class,
        'sender' => ActivitySender::class,
        'turn' => Turn::class,
        'created_at' => 'immutable_datetime',
    ];

    protected static string $factory = TicketActivityFactory::class;

    protected static function boot(): void
    {
        parent::boot();

        static::observe(TicketActivityObserver::class);
    }

    /**
     * @return Relations\PanelAwareBelongsTo<Ticket,$this>
     */
    public function ticket(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(Ticket::class),
            'ticket'
        );
    }

    /**
     * @return Relations\PanelAwareBelongsTo<Model&Authenticatable, $this>
     */
    public function user(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveUserModelClass(),
            'user'
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

            return resolve(GetUserDisplayName::class)($this->user_id);
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
            ActivityType::AssigneeChanged => filled($this->data['to'])
                ? __('padmission-tickets::activities.assigned_to', ['name' => resolve(GetUserDisplayName::class)($this->data['to'])])
                : __('padmission-tickets::activities.unassigned'),
            ActivityType::TurnChanged => __('padmission-tickets::activities.turn_changed', [
                'from' => Turn::tryFrom($this->data['from'])->getLabel(),
                'to' => Turn::tryFrom($this->data['to'])->getLabel(),
            ]),
            ActivityType::StatusChanged => __('padmission-tickets::activities.status_changed', [
                'from' => TicketStatus::withoutGlobalScope(CurrentPanelScope::class)->find($this->data['from'])->display_name,
                'to' => TicketStatus::withoutGlobalScope(CurrentPanelScope::class)->find($this->data['to'])->display_name,
            ]),
            ActivityType::PriorityChanged => __('padmission-tickets::activities.priority_changed', [
                'from' => TicketPriority::withoutGlobalScope(CurrentPanelScope::class)->find($this->data['from'])->display_name,
                'to' => TicketPriority::withoutGlobalScope(CurrentPanelScope::class)->find($this->data['to'])->display_name,
            ]),
            default => $value
        });
    }
}
