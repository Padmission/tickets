<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Copilot\Models\Concerns\HasCopilotTenant;

class CopilotTokenUsage extends Model
{
    use HasCopilotTenant;
    use HasUlids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'usage_date' => 'date',
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

    public function scopeForDate($query, $date)
    {
        return $query->where('usage_date', $date);
    }

    public function scopeForToday($query)
    {
        return $query->whereDate('usage_date', now()->toDateString());
    }

    public function scopeForParticipant($query, Model $participant)
    {
        return $query
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey());
    }

    public static function record(
        Model $participant,
        string $panelId,
        int $inputTokens,
        int $outputTokens,
        ?Model $tenant = null,
        ?CopilotConversation $conversation = null,
        ?string $model = null,
        ?string $provider = null,
    ): static {
        return static::create([
            'conversation_id' => $conversation?->id,
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
            'panel_id' => $panelId,
            'tenant_type' => $tenant?->getMorphClass(),
            'tenant_id' => $tenant?->getKey(),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'model' => $model,
            'provider' => $provider,
            'usage_date' => now()->toDateString(),
        ]);
    }
}
