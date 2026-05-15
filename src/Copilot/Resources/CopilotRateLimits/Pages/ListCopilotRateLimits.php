<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Pages;

use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\CopilotRateLimitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCopilotRateLimits extends ListRecords
{
    protected static string $resource = CopilotRateLimitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
