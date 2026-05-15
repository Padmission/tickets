<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Copilot\Models\Concerns\HasCopilotTenant;
use Padmission\Tickets\Copilot\Models\Scopes\CopilotTenantScope;

class CopilotConversation extends Model
{
    use HasCopilotTenant;
    use HasUlids;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $conversation): void {
            $force = $conversation->isForceDeleting();
            $conversation->messages()
                ->withoutGlobalScope(CopilotTenantScope::class)
                ->when(! $force, fn ($q) => $q->whereNull('deleted_at'))
                ->when($force, fn ($q) => $q->withTrashed())
                ->get()
                ->each(fn (CopilotMessage $message) => $force ? $message->forceDelete() : $message->delete());
        });

        static::restoring(function (self $conversation): void {
            $conversation->messages()
                ->withoutGlobalScope(CopilotTenantScope::class)
                ->onlyTrashed()
                ->get()
                ->each(fn (CopilotMessage $message) => $message->restore());
        });
    }

    protected $fillable = [
        'participant_type',
        'participant_id',
        'panel_id',
        'tenant_type',
        'tenant_id',
        'title',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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

    public function messages(): HasMany
    {
        return $this->hasMany(CopilotMessage::class, 'conversation_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CopilotAuditLog::class, 'conversation_id');
    }

    public function tokenUsages(): HasMany
    {
        return $this->hasMany(CopilotTokenUsage::class, 'conversation_id');
    }

    public function latestMessage(): HasMany
    {
        return $this->messages()->latest()->limit(1);
    }

    public function scopeForPanel($query, string $panelId)
    {
        return $query->where('panel_id', $panelId);
    }

    public function scopeForParticipant($query, Model $participant)
    {
        return $query
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey());
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

    public function getTotalTokensAttribute(): int
    {
        return $this->messages()->sum('input_tokens') + $this->messages()->sum('output_tokens');
    }
}
