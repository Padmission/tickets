<?php

namespace Padmission\Tickets\Filament\Resources\Priorities\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Padmission\Tickets\Filament\Resources\Priorities\PriorityResource;

class ListPriorities extends ListRecords
{
    protected static string $resource = PriorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->slideOver()->modalWidth(MaxWidth::Medium),
        ];
    }
}
