<?php

namespace Padmission\Tickets\Filament\Resources\Statuses\Pages;

use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Padmission\Tickets\Filament\Resources\Statuses\StatusResource;

class ListStatuses extends ListRecords
{
    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->slideOver()->modalWidth(Width::Medium),
        ];
    }
}
