<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Pages;

use Filament\Resources\Pages\CreateRecord;
use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\CopilotRateLimitResource;

class CreateCopilotRateLimit extends CreateRecord
{
    protected static string $resource = CopilotRateLimitResource::class;
}
