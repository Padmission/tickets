<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Tools;

use Padmission\Tickets\Copilot\Enums\MessageRole;
use Padmission\Tickets\Copilot\Enums\ToolCallStatus;
use Padmission\Tickets\Copilot\Events\CopilotToolExecuted;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Copilot\Models\CopilotToolCall;
use Padmission\Tickets\Copilot\Tools\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Contracts\Tool;

abstract class BaseTool implements Tool
{
    use LogsAudit;

    protected string $panelId;

    protected Model $user;

    protected ?Model $tenant = null;

    protected ?string $conversationId = null;

    public function forPanel(string $panelId): static
    {
        $this->panelId = $panelId;

        return $this;
    }

    public function forUser(Model $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function forTenant(?Model $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function forConversation(?string $conversationId): static
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    protected function dispatchToolExecuted(string $toolName, string $result, ?string $messageId = null, ?array $input = null): void
    {
        $messageId ??= $this->resolveMessageId();

        $toolCall = new CopilotToolCall([
            'message_id' => $messageId,
            'tool_name' => $toolName,
            'tool_input' => $input ?? [],
            'tool_output' => $result,
            'status' => ToolCallStatus::Executed,
        ]);

        if ($messageId && config('filament-copilot.audit.enabled', true) && config('filament-copilot.audit.log_tool_calls', true)) {
            $toolCall->save();
        }

        event(new CopilotToolExecuted(
            toolCall: $toolCall,
            toolName: $toolName,
            result: $result,
        ));
    }

    protected function resolveMessageId(): ?string
    {
        if (! isset($this->panelId, $this->user) || ! $this->conversationId) {
            return null;
        }

        $conversation = CopilotConversation::query()
            ->forPanel($this->panelId)
            ->forParticipant($this->user)
            ->forTenant($this->tenant)
            ->find($this->conversationId);

        if (! $conversation) {
            return null;
        }

        return $conversation->messages()
            ->where('role', MessageRole::User)
            ->latest('created_at')
            ->value('id');
    }
}
