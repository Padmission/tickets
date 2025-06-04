<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Carbon\CarbonImmutable;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Padmission\Tickets\Filament\Infolists\Components\AvatarEntry;
use Padmission\Tickets\Filament\Infolists\Components\SubmitterEntry;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getHeading(): string|Htmlable
    {
        /**
         * @var Ticket $ticket
         */
        $ticket = $this->record;

        return $ticket->subject;
    }

    protected function getHeaderActions(): array
    {
        return [
            CloseTicketAction::make(),

            EditTicketAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(3)
            ->schema([
                Section::make()
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'pad-ti-section-chat'])
                    ->schema([
                        ViewEntry::make('chat')->view('padmission-tickets::filament.infolists.chat'),
                    ]),

                Grid::make()->columnSpan(1)->columns(2)->schema([
                    Section::make()->schema([
                        TextEntry::make('status.display_name')
                            ->label(__('padmission-tickets::tickets.resources.tickets.status'))
                            ->badge()
                            ->color(fn (Ticket $record) => $record->status->colorPalette),

                        TextEntry::make('priority.display_name')
                            ->badge()
                            ->color(fn (Ticket $record) => $record->priority->colorPalette)
                            ->label(__('padmission-tickets::tickets.resources.tickets.priority')),

                        SubmitterEntry::make('submitter')
                            ->label(__('padmission-tickets::tickets.resources.tickets.submitter'))
                            ->columnSpanFull(),

                        AvatarEntry::make('assignee')
                            ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                            ->columnSpanFull(),

                        TextEntry::make('turn')
                            ->label(__('padmission-tickets::tickets.resources.tickets.turn'))
                            ->columnSpanFull(),

                        TextEntry::make('latestMessage.created_at')
                            ->label(__('padmission-tickets::tickets.resources.tickets.last_message'))
                            ->hidden(fn (Ticket $record) => $record->isClosed)
                            ->dateTime()
                            ->formatStateUsing(fn (?CarbonImmutable $state) => $state?->diffForHumans())
                            ->tooltip(fn (?CarbonImmutable $state) => $state?->format(Table::$defaultDateTimeDisplayFormat))
                            ->columnSpanFull(),

                        TextEntry::make('closed_at')
                            ->label(__('padmission-tickets::tickets.resources.tickets.closed_at'))
                            ->visible(fn (Ticket $record) => $record->isClosed)
                            ->dateTime()
                            ->formatStateUsing(fn ($state) => $state?->diffForHumans())
                            ->tooltip(fn ($state) => $state?->format(Table::$defaultDateTimeDisplayFormat))
                            ->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
