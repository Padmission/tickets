<?php

namespace Padmission\Tickets\Filament\Resources\Dispositions\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Padmission\Tickets\Filament\Resources\Dispositions\DispositionResource;

class ListDispositions extends ListRecords
{
    protected static string $resource = DispositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->slideOver()->modalWidth(MaxWidth::Medium),
        ];
    }
}
