<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Copilot\Models\Concerns\HasCopilotTenant;

class CopilotRateLimit extends Model
{
    use HasCopilotTenant;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'panel_id',
        'tenant_type',
        'tenant_id',
        'participant_type',
        'participant_id',
        'max_messages_per_hour',
        'max_messages_per_day',
        'max_tokens_per_hour',
        'max_tokens_per_day',
        'is_blocked',
        'copilot_enabled',
        'blocked_until',
        'blocked_reason',
    ];

    protected function casts(): array
    {
        return [
            'max_messages_per_hour' => 'integer',
            'max_messages_per_day' => 'integer',
            'max_tokens_per_hour' => 'integer',
            'max_tokens_per_day' => 'integer',
            'is_blocked' => 'boolean',
            'copilot_enabled' => 'boolean',
            'blocked_until' => 'datetime',
        ];
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

    public function scopeForTenant($query, ?Model $tenant)
    {
        if ($tenant === null) {
            return $query->whereNull('tenant_type')->whereNull('tenant_id');
        }

        return $query
            ->where('tenant_type', $tenant->getMorphClass())
            ->where('tenant_id', $tenant->getKey());
    }

    public function scopeForParticipant($query, ?Model $participant)
    {
        if ($participant === null) {
            return $query->whereNull('participant_type')->whereNull('participant_id');
        }

        return $query
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey());
    }

    public function isCurrentlyBlocked(): bool
    {
        if (! $this->is_blocked) {
            return false;
        }

        if ($this->blocked_until && $this->blocked_until->isPast()) {
            $this->update(['is_blocked' => false, 'blocked_until' => null, 'blocked_reason' => null]);

            return false;
        }

        return true;
    }

    public function block(?string $reason = null, ?\DateTimeInterface $until = null): void
    {
        $this->update([
            'is_blocked' => true,
            'blocked_reason' => $reason,
            'blocked_until' => $until,
        ]);
    }

    public function unblock(): void
    {
        $this->update([
            'is_blocked' => false,
            'blocked_reason' => null,
            'blocked_until' => null,
        ]);
    }

    public function disableCopilot(): void
    {
        $this->update(['copilot_enabled' => false]);
    }

    public function enableCopilot(): void
    {
        $this->update(['copilot_enabled' => true]);
    }
}
