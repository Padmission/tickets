<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Events;

use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Illuminate\Foundation\Events\Dispatchable;

class CopilotMessageSent
{
    use Dispatchable;

    public function __construct(
        public readonly CopilotConversation $conversation,
        public readonly string $content,
        public readonly string $panelId,
    ) {}
}
