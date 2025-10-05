<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Carbon\CarbonImmutable;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Filament\Infolists\Components\AvatarEntry;
use Padmission\Tickets\Filament\Infolists\Components\SubmitterEntry;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class ViewTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected $listeners = ['refresh' => '$refresh'];

    public function getBreadcrumb(): string
    {
        return 'View';
    }

    public function getHeading(): string|Htmlable
    {
        /**
         * @var Ticket $ticket
         */
        $ticket = $this->record;

        return new HtmlString($ticket->subject);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateLinkedTicketAction::make(),
            CloseTicketAction::make(),
            EditTicketAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                Section::make()
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'pad-ti-chat-section'])
                    ->schema([
                        ViewEntry::make('chat')->view('padmission-tickets::filament.infolists.chat'),
                    ]),

                Grid::make()->columns(1)->schema([
                    Section::make()->columns(2)->schema([
                        TextEntry::make('status.display_name')
                            ->label(__('padmission-tickets::tickets.resources.tickets.status'))
                            ->badge()
                            ->color(fn (Ticket $record) => $record->status->colorPalette),

                        TextEntry::make('priority.display_name')
                            ->badge()
                            ->color(fn (Ticket $record) => $record->priority->colorPalette)
                            ->label(__('padmission-tickets::tickets.resources.tickets.priority')),

                        TextEntry::make('disposition.display_name')
                            ->badge()
                            ->color(fn (Ticket $record) => $record->disposition?->colorPalette)
                            ->label(__('padmission-tickets::tickets.resources.tickets.disposition'))
                            ->hidden(fn (Ticket $record) => ! $record->disposition_id),

                        SubmitterEntry::make('submitter')
                            ->label(__('padmission-tickets::tickets.resources.tickets.submitter'))
                            ->columnSpanFull(),

                        AvatarEntry::make('assignee')
                            ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                            ->columnSpanFull(),

                        TextEntry::make('turn')
                            ->label(__('padmission-tickets::tickets.resources.tickets.turn'))
                            ->columnSpanFull(),

                        TextEntry::make('source_panel')
                            ->label(__('padmission-tickets::tickets.resources.tickets.source_panel'))
                            ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                            ->visible(fn () => TicketResource::shouldShowSourcePanel())
                            ->columnSpanFull(),

                        TextEntry::make('latestMessage.created_at')
                            ->label(__('padmission-tickets::tickets.resources.tickets.last_message'))
                            ->hidden(fn (Ticket $record) => $record->isClosed)
                            ->dateTime()
                            ->formatStateUsing(fn (?CarbonImmutable $state) => $state?->diffForHumans())
                            ->tooltip(fn (?CarbonImmutable $state) => $state?->format(TicketPlugin::get()->getDateTimeDisplayFormat()))
                            ->columnSpanFull(),

                        TextEntry::make('closed_at')
                            ->label(__('padmission-tickets::tickets.resources.tickets.closed_at'))
                            ->visible(fn (Ticket $record) => $record->isClosed)
                            ->dateTime()
                            ->formatStateUsing(fn ($state) => $state?->diffForHumans())
                            ->tooltip(fn ($state) => $state?->format(TicketPlugin::get()->getDateTimeDisplayFormat()))
                            ->columnSpanFull(),
                    ]),

                    Section::make()
                        ->columns(2)
                        ->heading(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
                        ->visible(fn () => TicketPlugin::get()->hasLinkedTickets())
                        ->compact()
                        ->schema([
                            LinkedTicketModalSelect::make('parentTicket')
                                ->relationship('parentTicket', 'subject')
                                ->label(__('padmission-tickets::tickets.resources.tickets.parent_ticket'))
                                ->visible(fn () => count(TicketPlugin::get()->getPanelsForLinkedTicketCreation()) > 0)
                                ->afterStateUpdated(function (Ticket $record, $state) {
                                    $record->update(['linked_ticket_id' => $state]);
                                }),

                            LinkedTicketModalSelect::make('childTickets')
                                ->relationship('childTickets', 'subject')
                                ->multiple()
                                ->nullable()
                                ->visible(fn () => count(TicketPlugin::get()->getLinkedTicketSourcePanels()) > 0)
                                ->label(__('padmission-tickets::tickets.resources.tickets.child_tickets'))
                                ->afterStateUpdated(function (Ticket $record, $state) {
                                    // @TODO: Should this be recorded by Activity Log?
                                    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

                                    DB::beginTransaction();

                                    $ticketModel::query()
                                        ->where('linked_ticket_id', $record->getKey())
                                        ->whereNotIn('id', $state)
                                        ->update(['linked_ticket_id' => null]);

                                    $ticketModel::query()
                                        ->whereIn('id', $state)
                                        ->update(['linked_ticket_id' => $record->getKey()]);

                                    DB::commit();
                                }),
                        ]),
                ]),
            ]);
    }
}
