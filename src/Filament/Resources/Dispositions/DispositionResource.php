<?php

namespace Padmission\Tickets\Filament\Resources\Dispositions;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\ActionGroup;
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
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\TicketPlugin;

class DispositionResource extends Resource
{
    protected static ?string $slug = 'dispositions';

    public static function getModel(): string
    {
        return TicketPlugin::resolveModelClass(TicketDisposition::class);
    }

    public static function getModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.dispositions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.dispositions.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('padmission-tickets::tickets.resources.navigation_group');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-clipboard';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('display_name')
                    ->label(__('padmission-tickets::tickets.resources.dispositions.display_name'))
                    ->required(),

                ColorSelect::make('color')
                    ->label(__('padmission-tickets::tickets.resources.dispositions.color'))
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
            ->actions([
                ActionGroup::make([
                    EditAction::make()->slideOver()->modalWidth(MaxWidth::Medium),
                    DeleteAction::make(),
                ])
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
            'index' => Pages\ListDispositions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->tap(new CurrentPanelScope);
    }
}
