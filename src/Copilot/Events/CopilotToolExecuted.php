<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CopilotToolExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly array $toolCall,
        public readonly string $toolName,
        public readonly string $result,
    ) {}
}
