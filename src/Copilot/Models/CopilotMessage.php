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
use Padmission\Tickets\Copilot\Enums\MessageRole;
use Padmission\Tickets\Copilot\Models\Concerns\HasCopilotTenant;
use Padmission\Tickets\Copilot\Models\Scopes\CopilotTenantScope;

class CopilotMessage extends Model
{
    use HasCopilotTenant;
    use HasUlids;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $message): void {
            $force = $message->isForceDeleting();
            $message->toolCalls()
                ->withoutGlobalScope(CopilotTenantScope::class)
                ->when(! $force, fn ($q) => $q->whereNull('deleted_at'))
                ->when($force, fn ($q) => $q->withTrashed())
                ->get()
                ->each(fn (CopilotToolCall $call) => $force ? $call->forceDelete() : $call->delete());
        });

        static::restoring(function (self $message): void {
            $message->toolCalls()
                ->withoutGlobalScope(CopilotTenantScope::class)
                ->onlyTrashed()
                ->get()
                ->each(fn (CopilotToolCall $call) => $call->restore());
        });
    }

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata',
        'input_tokens',
        'output_tokens',
        'tenant_type',
        'tenant_id',
    ];

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CopilotConversation::class, 'conversation_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(CopilotToolCall::class, 'message_id');
    }

    public function scopeByRole($query, MessageRole $role)
    {
        return $query->where('role', $role);
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function isToolMessage(): bool
    {
        return $this->role === MessageRole::Tool;
    }

    public function getTotalTokensAttribute(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }
}
