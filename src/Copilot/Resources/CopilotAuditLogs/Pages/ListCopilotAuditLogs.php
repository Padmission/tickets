<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotAuditLogs\Pages;

use Filament\Resources\Pages\ListRecords;
use Padmission\Tickets\Copilot\Resources\CopilotAuditLogs\CopilotAuditLogResource;

class ListCopilotAuditLogs extends ListRecords
{
    protected static string $resource = CopilotAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
