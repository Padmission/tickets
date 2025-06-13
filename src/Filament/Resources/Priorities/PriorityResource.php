<?php

namespace Padmission\Tickets\Filament\Resources\Priorities;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Filament\Forms\Components\ColorSelect;
use Padmission\Tickets\Filament\Resources\Concerns\HasResourceConfiguration;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\TicketPlugin;

class PriorityResource extends Resource
{
    use HasResourceConfiguration;

    protected static ?string $slug = 'priorities';

    protected static ?string $model = TicketPriority::class;

    public static function canAccess(): bool
    {
        if (! Gate::getPolicyFor(TicketPriority::class)) {
            return Filament::auth()->user()->can('viewAny', TicketPlugin::resolveModelClass(Ticket::class));
        }

        return parent::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('display_name')
                    ->label(__('padmission-tickets::tickets.resources.priorities.display_name'))
                    ->columnSpanFull()
                    ->required(),

                ColorSelect::make('color')
                    ->label(__('padmission-tickets::tickets.resources.priorities.color'))
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
                    ->label(__('padmission-tickets::tickets.resources.priorities.color'))
                    ->getStateUsing(fn (TicketPriority $record) => 'rgb('.Color::{$record->color}[600].')'),

                TextColumn::make('display_name')
                    ->label(__('padmission-tickets::tickets.resources.priorities.display_name')),
            ])
            ->actions([
                EditAction::make()->slideOver()->modalWidth(MaxWidth::Medium),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriorities::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
