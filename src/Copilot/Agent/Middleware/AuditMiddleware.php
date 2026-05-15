<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Agent\Middleware;

use Closure;
use Padmission\Tickets\Copilot\Enums\AuditAction;
use Padmission\Tickets\Copilot\Models\CopilotAuditLog;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Prompts\AgentPrompt;

class AuditMiddleware
{
    public function __construct(
        protected string $panelId,
        protected Model $user,
        protected ?Model $tenant = null,
        protected ?string $conversationId = null,
    ) {}

    public function withConversationId(string $id): static
    {
        $this->conversationId = $id;

        return $this;
    }

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        if (! config('filament-copilot.audit.enabled', true)) {
            return $next($prompt);
        }

        if (config('filament-copilot.audit.log_messages', true)) {
            AuditMiddleware::logAction(
                AuditAction::MessageSent,
                $this->user,
                $this->panelId,
                $this->tenant,
                $this->conversationId,
            );
        }

        $response = $next($prompt);

        if (config('filament-copilot.audit.log_messages', true)) {
            AuditMiddleware::logAction(
                AuditAction::ResponseReceived,
                $this->user,
                $this->panelId,
                $this->tenant,
                $this->conversationId,
            );
        }

        return $response;
    }

    public static function logAction(
        AuditAction $action,
        Model $user,
        string $panelId,
        ?Model $tenant = null,
        ?string $conversationId = null,
        ?string $resourceType = null,
        ?string $recordKey = null,
        ?array $payload = null,
    ): void {
        $conversation = $conversationId
            ? CopilotConversation::query()
                ->forPanel($panelId)
                ->forParticipant($user)
                ->forTenant($tenant)
                ->find($conversationId)
            : null;

        CopilotAuditLog::log(
            action: $action,
            participant: $user,
            panelId: $panelId,
            tenant: $tenant,
            conversation: $conversation,
            resourceType: $resourceType,
            recordKey: $recordKey,
            payload: $payload,
        );
    }
}
