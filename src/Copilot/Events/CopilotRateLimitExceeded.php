<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class CopilotRateLimitExceeded
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly string $panelId,
        public readonly ?Model $tenant = null,
    ) {}
}
