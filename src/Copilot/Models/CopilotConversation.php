<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Padmission\Tickets\Copilot\Models\Concerns\HasCopilotTenant;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;

class CopilotConversation extends Model
{
    use HasCopilotTenant;
    use HasUlids;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $conversation): void {
            //
        });

        static::restoring(function (self $conversation): void {
            //
        });
    }

    protected $guarded = ['id'];

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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CopilotAuditLog::class, 'conversation_id');
    }

    public function tokenUsages(): HasMany
    {
        return $this->hasMany(CopilotTokenUsage::class, 'conversation_id');
    }

    public function getMessages(): Collection
    {
        return $this->ticket?->ticketActivities()
            ->where('type', ActivityType::Message)
            ->oldest()
            ->get() ?? collect();
    }

    public function latestMessage()
    {
        return $this->ticket?->latestMessage();
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
        $ticket = $this->ticket;

        if (! $ticket) {
            return 0;
        }

        return (int) $ticket->ticketActivities()
            ->where('sender', 'ai')
            ->get()
            ->sum(fn ($activity) => (int) data_get($activity->data, 'input_tokens') + (int) data_get($activity->data, 'output_tokens'));
    }
}
