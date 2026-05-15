<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Pages;

use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\CopilotRateLimitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCopilotRateLimit extends EditRecord
{
    protected static string $resource = CopilotRateLimitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
