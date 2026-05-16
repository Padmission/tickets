<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotConversations\Pages;

use Filament\Resources\Pages\ListRecords;
use Padmission\Tickets\Copilot\Resources\CopilotConversations\CopilotConversationResource;

class ListCopilotConversations extends ListRecords
{
    protected static string $resource = CopilotConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
