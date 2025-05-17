<?php

namespace Padmission\Tickets\Filament\Resources\Tickets;

use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketResource extends Resource
{
    protected static ?string $slug = 'tickets';

    public static function getModel(): string
    {
        return config('padmission-tickets.models.ticket');
    }

    public static function getModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.tickets.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.tickets.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('padmission-tickets::tickets.resources.navigation_group');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return new HtmlString(<<<HTML
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-headset-icon lucide-headset"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/></svg>
        HTML);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('subject')
                    ->required(),

                TextInput::make('status_id')
                    ->required()
                    ->integer(),

                TextInput::make('priority_id')
                    ->required()
                    ->integer(),

                Select::make('assignee_id')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->required(),

                TextInput::make('submitter_id')
                    ->integer(),

                TextInput::make('submitter_email'),

                TextInput::make('turn')
                    ->required(),

                DatePicker::make('closed_at')
                    ->label('Closed Date'),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn (?Ticket $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn (?Ticket $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at')
            ->columns([
                TextColumn::make('status.display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->status->colorPalette)
                    ->sortable(),

                TextColumn::make('priority.display_name')
                    ->label(__('padmission-tickets::tickets.resources.priorities.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->priority->colorPalette)
                    ->sortable(),

                IconColumn::make('turn')
                    ->label(__('padmission-tickets::tickets.resources.tickets.turn'))
                    ->tooltip(fn (Ticket $record) => $record->turn->getLabel())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->searchable(),

                TextColumn::make('assignee.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->relationship('status', 'display_name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('priority')
                    ->relationship('priority', 'display_name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('assignee')
                    ->relationship('assignee', 'name')
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
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
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}/view'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $plugin = TicketPlugin::get();

        return parent::getEloquentQuery()->where('escalation_level', $plugin->getEscalationLevel());
    }
}
