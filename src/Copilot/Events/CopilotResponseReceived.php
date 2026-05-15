<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Events;

use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Copilot\Models\CopilotMessage;
use Illuminate\Foundation\Events\Dispatchable;

class CopilotResponseReceived
{
    use Dispatchable;

    public function __construct(
        public readonly CopilotConversation $conversation,
        public readonly CopilotMessage $message,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
    ) {}
}
