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
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Filament\Forms\Fields\ColorSelect;
use Padmission\Tickets\Models\Priority;

class PriorityResource extends Resource
{
    protected static ?string $slug = 'priorities';

    public static function getModel(): string
    {
        return config('padmission-tickets.models.priority');
    }

    public static function getModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.priorities.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.priorities.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('padmission-tickets::tickets.resources.navigation_group');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return new HtmlString(<<<'HTML'
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flag-icon lucide-flag"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>
        HTML);
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
                    ->getStateUsing(fn (Priority $record) => 'rgb('.Color::{$record->color}[600].')'),

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
        return parent::getEloquentQuery()
            ->where('panel', Filament::getCurrentPanel()->getId());
    }
}
