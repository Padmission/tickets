<?php

namespace Padmission\Tickets\Filament\Resources\Statuses;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
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
use Padmission\Tickets\Filament\Forms\Components\ColorSelect;
use Padmission\Tickets\Models\Status;
use Padmission\Tickets\TicketPlugin;

class StatusResource extends Resource
{
    protected static ?string $slug = 'statuses';

    public static function getModel(): string
    {
        return TicketPlugin::resolveModelClass(Status::class);
    }

    public static function getModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.statuses.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.statuses.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('padmission-tickets::tickets.resources.navigation_group');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return new HtmlString(<<<'HTML'
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-tag-icon lucide-tag"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>
        HTML);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.display_name'))
                    ->required(),

                ColorSelect::make('color')
                    ->label(__('padmission-tickets::tickets.resources.statuses.color'))
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
                    ->label(__('padmission-tickets::tickets.resources.statuses.color'))
                    ->getStateUsing(fn (Status $record) => 'rgb('.$record->colorPalette[600].')'),

                TextColumn::make('display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.display_name')),
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
            'index' => Pages\ListStatuses::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('panel', Filament::getCurrentPanel()->getId());
    }
}
