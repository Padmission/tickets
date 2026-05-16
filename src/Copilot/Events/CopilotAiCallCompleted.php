<?php

namespace Padmission\Tickets\Copilot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CopilotAiCallCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $durationMs,
        public bool $success,
        public ?array $requestBody = null,
        public ?array $responseBody = null,
        public ?int $ticketId = null,
        public ?int $userId = null,
        public int|string|null $tenantId = null,
        public string $feature = 'copilot:app',
    ) {}
}
