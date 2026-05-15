<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Copilot\Enums\AuditAction;
use Padmission\Tickets\Copilot\Models\Concerns\HasCopilotTenant;

class CopilotAuditLog extends Model
{
    use HasCopilotTenant;
    use HasUlids;
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'participant_type',
        'participant_id',
        'panel_id',
        'tenant_type',
        'tenant_id',
        'action',
        'resource_type',
        'record_key',
        'payload',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CopilotConversation::class, 'conversation_id');
    }

    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForPanel($query, string $panelId)
    {
        return $query->where('panel_id', $panelId);
    }

    public function scopeForAction($query, AuditAction $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForParticipant($query, Model $participant)
    {
        return $query
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey());
    }

    public static function log(
        AuditAction $action,
        Model $participant,
        string $panelId,
        ?Model $tenant = null,
        ?CopilotConversation $conversation = null,
        ?string $resourceType = null,
        ?string $recordKey = null,
        ?array $payload = null,
    ): static {
        return static::create([
            'conversation_id' => $conversation?->id,
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
            'panel_id' => $panelId,
            'tenant_type' => $tenant?->getMorphClass(),
            'tenant_id' => $tenant?->getKey(),
            'action' => $action,
            'resource_type' => $resourceType,
            'record_key' => $recordKey,
            'payload' => $payload,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
