<?php

namespace Padmission\Tickets\Filament\Resources\Dispositions;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Padmission\Tickets\Filament\Resources\Dispositions\Pages\ListDispositions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Filament\Forms\Components\ColorSelect;
use Padmission\Tickets\Filament\Resources\Concerns\HasResourceConfiguration;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\TicketPlugin;

class DispositionResource extends Resource
{
    use HasResourceConfiguration;

    protected static ?string $slug = 'dispositions';

    protected static ?string $model = TicketDisposition::class;

    public static function canAccess(): bool
    {
        if (! Gate::getPolicyFor(static::getModel())) {
            return Filament::auth()->user()->can('viewAny', TicketPlugin::resolveModelClass(Ticket::class));
        }

        return parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('display_name')
                    ->label(__('padmission-tickets::tickets.resources.dispositions.display_name'))
                    ->columnSpanFull()
                    ->required(),

                ColorSelect::make('color')
                    ->label(__('padmission-tickets::tickets.resources.dispositions.color'))
                    ->columnSpanFull()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order', 'asc')
            ->columns([
                ColorColumn::make('color')
                    ->label(__('padmission-tickets::tickets.resources.dispositions.color'))
                    ->getStateUsing(fn (TicketDisposition $record) => 'rgb('.$record->colorPalette[600].')'),

                TextColumn::make('display_name')
                    ->label(__('padmission-tickets::tickets.resources.dispositions.display_name')),
            ])
            ->recordActions([
                EditAction::make()->slideOver()->modalWidth(Width::Medium),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispositions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
