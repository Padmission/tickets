<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Copilot\Enums\ToolCallStatus;
use Padmission\Tickets\Copilot\Models\Concerns\HasCopilotTenant;

class CopilotToolCall extends Model
{
    use HasCopilotTenant;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'message_id',
        'tool_name',
        'tool_input',
        'tool_output',
        'status',
        'requires_approval',
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
            'tool_input' => 'array',
            'status' => ToolCallStatus::class,
            'requires_approval' => 'boolean',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CopilotMessage::class, 'message_id');
    }

    public function isPending(): bool
    {
        return $this->status === ToolCallStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ToolCallStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ToolCallStatus::Rejected;
    }

    public function isExecuted(): bool
    {
        return $this->status === ToolCallStatus::Executed;
    }

    public function approve(): void
    {
        $this->update(['status' => ToolCallStatus::Approved]);
    }

    public function reject(): void
    {
        $this->update(['status' => ToolCallStatus::Rejected]);
    }

    public function markExecuted(string $output): void
    {
        $this->update([
            'status' => ToolCallStatus::Executed,
            'tool_output' => $output,
        ]);
    }

    public function markFailed(string $output): void
    {
        $this->update([
            'status' => ToolCallStatus::Failed,
            'tool_output' => $output,
        ]);
    }
}
