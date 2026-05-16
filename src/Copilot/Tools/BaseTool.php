<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Tools;

use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Contracts\Tool;
use Padmission\Tickets\Copilot\Events\CopilotToolExecuted;
use Padmission\Tickets\Copilot\Tools\Concerns\LogsAudit;

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
        event(new CopilotToolExecuted(
            toolCall: [
                'message_id' => $messageId ?? $this->resolveMessageId(),
                'tool_name' => $toolName,
                'tool_input' => $input ?? [],
                'tool_output' => $result,
                'status' => 'executed',
            ],
            toolName: $toolName,
            result: $result,
        ));
    }

    protected function resolveMessageId(): ?string
    {
        return null;
    }
}
