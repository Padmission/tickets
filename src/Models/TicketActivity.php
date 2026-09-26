<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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
#[ObservedBy(TicketActivityObserver::class)]
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

    public function plainTextContent(?int $words = null): ?string
    {
        $content = $this->content;

        if ($content === null) {
            return null;
        }

        $spacedContent = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|h[1-6]|blockquote|pre|tr)>/i', ' ', (string) $content) ?? (string) $content;
        $plainText = Str::squish(html_entity_decode(strip_tags($spacedContent), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($plainText === '') {
            return null;
        }

        return $words === null ? $plainText : Str::words($plainText, $words);
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

            if ($this->relationLoaded('user') && $this->user !== null) {
                return resolve(GetUserDisplayName::class)->forUser($this->user);
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
            ActivityType::AssigneeChanged => filled($this->activityData('to'))
                ? __('padmission-tickets::activities.assigned_to', ['name' => resolve(GetUserDisplayName::class)($this->activityData('to'))])
                : __('padmission-tickets::activities.unassigned'),
            ActivityType::TurnChanged => __('padmission-tickets::activities.turn_changed', [
                'from' => $this->turnLabel($this->activityData('from')),
                'to' => $this->turnLabel($this->activityData('to')),
            ]),
            ActivityType::StatusChanged => __('padmission-tickets::activities.status_changed', [
                'from' => $this->statusLabel($this->activityData('from')),
                'to' => $this->statusLabel($this->activityData('to')),
            ]),
            ActivityType::PriorityChanged => __('padmission-tickets::activities.priority_changed', [
                'from' => $this->priorityLabel($this->activityData('from')),
                'to' => $this->priorityLabel($this->activityData('to')),
            ]),
            ActivityType::AddedToEscalation => __('padmission-tickets::activities.added_to_escalation', ['id' => $this->activityData('escalation'), 'name' => $this->actorName()]),
            ActivityType::RemovedFromEscalation => __('padmission-tickets::activities.removed_from_escalation', ['id' => $this->activityData('escalation'), 'name' => $this->actorName()]),
            ActivityType::OriginalAdded => __('padmission-tickets::activities.original_added', ['id' => $this->activityData('original'), 'name' => $this->actorName()]),
            ActivityType::OriginalRemoved => __('padmission-tickets::activities.original_removed', ['id' => $this->activityData('original'), 'name' => $this->actorName()]),
            default => $value
        });
    }

    protected function actorName(): string
    {
        if ($this->relationLoaded('user') && $this->user !== null) {
            return resolve(GetUserDisplayName::class)->forUser($this->user);
        }

        return resolve(GetUserDisplayName::class)($this->user_id);
    }

    protected function activityData(string $key): mixed
    {
        return data_get($this->data ?? [], $key);
    }

    protected function turnLabel(mixed $value): string
    {
        if (! is_string($value)) {
            return $this->unknownLabel();
        }

        return Turn::tryFrom($value)?->getLabel() ?? $this->unknownLabel();
    }

    protected function statusLabel(mixed $id): string
    {
        return TicketStatus::withoutGlobalScope(CurrentPanelScope::class)
            ->find($id)
            ->display_name ?? $this->unknownLabel();
    }

    protected function priorityLabel(mixed $id): string
    {
        return TicketPriority::withoutGlobalScope(CurrentPanelScope::class)
            ->find($id)
            ->display_name ?? $this->unknownLabel();
    }

    protected function unknownLabel(): string
    {
        return __('padmission-tickets::activities.unknown');
    }
}
