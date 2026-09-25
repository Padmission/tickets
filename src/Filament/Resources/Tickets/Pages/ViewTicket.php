<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Carbon\CarbonImmutable;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Filament\Infolists\Components\AvatarEntry;
use Padmission\Tickets\Filament\Infolists\Components\SubmitterEntry;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ViewOriginalConversationAction;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Tables\ChildTicketsTable;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Filament\Tables\ParentTicketTable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class ViewTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected $listeners = ['refresh' => '$refresh'];

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    protected function canEdit(?Ticket $record): bool
    {
        return static::getResource()::canEdit($record);
    }

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
            ViewOriginalConversationAction::make(),
            ReassignTicketAction::make()->authorize(static::canEdit(...)),
            CreateLinkedTicketAction::make()->authorize(static::canEdit(...)),
            CloseTicketAction::make()->authorize(static::canEdit(...)),
            EditTicketAction::make()->authorize(static::canEdit(...)),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    #[On('message-sent')]
    public function rerenderAfterMessage()
    {
        $this->skipRender();
        $this->partiallyRenderSchemaComponent('form.turn');
        $this->partiallyRenderSchemaComponent('form.latestMessage.created_at');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->extraAttributes(['class' => 'pad-ti-chat-section'])
                    ->schema([
                        ViewEntry::make('chat')->view('padmission-tickets::filament.infolists.chat'),
                    ]),

                Grid::make()->columnSpan(1)->columns(1)->schema([
                    Section::make()->columns(2)->schema([
                        TextEntry::make('status.display_name')
                            ->label(__('padmission-tickets::tickets.resources.tickets.status'))
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('padmission-tickets::tickets.resources.tickets.hints.status'))
                            ->badge()
                            ->color(fn (Ticket $record) => $record->status->colorPalette),

                        TextEntry::make('priority.display_name')
                            ->badge()
                            ->color(fn (Ticket $record) => $record->priority->colorPalette)
                            ->label(__('padmission-tickets::tickets.resources.tickets.priority'))
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('padmission-tickets::tickets.resources.tickets.hints.priority')),

                        TextEntry::make('disposition.display_name')
                            ->badge()
                            ->color(fn (Ticket $record) => $record->disposition?->colorPalette)
                            ->label(__('padmission-tickets::tickets.resources.tickets.disposition'))
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('padmission-tickets::tickets.resources.tickets.hints.disposition'))
                            ->hidden(fn (Ticket $record) => ! $record->disposition_id),

                        ...TicketPlugin::get()->getAdditionalTicketDetails(),

                        SubmitterEntry::make('submitter')
                            ->label(__('padmission-tickets::tickets.resources.tickets.submitter'))
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('padmission-tickets::tickets.resources.tickets.hints.submitter'))
                            ->columnSpanFull(),

                        AvatarEntry::make('assignee')
                            ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('padmission-tickets::tickets.resources.tickets.hints.assignee'))
                            ->columnSpanFull(),

                        TextEntry::make('turn')
                            ->label(__('padmission-tickets::tickets.resources.tickets.turn'))
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('padmission-tickets::tickets.resources.tickets.hints.turn'))
                            ->badge()
                            ->color(fn (?Turn $state): string => $state === Turn::Supporter ? 'warning' : 'gray')
                            ->columnSpanFull(),

                        TextEntry::make('source_panel')
                            ->label(__('padmission-tickets::tickets.resources.tickets.source_panel'))
                            ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                            ->visible(fn () => TicketResource::shouldShowSourcePanel())
                            ->columnSpanFull(),

                        TextEntry::make('latestMessage.created_at')
                            ->label(__('padmission-tickets::tickets.resources.tickets.last_message'))
                            ->hidden(fn (Ticket $record) => $record->isClosed)
                            ->placeholder(__('padmission-tickets::tickets.resources.tickets.no_messages'))
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
                        ->description(fn (Ticket $record): ?string => static::describeEscalation($record))
                        ->visible(fn (Ticket $record) => TicketPlugin::get($record->panel)->hasLinkedTickets())
                        ->compact()
                        ->schema([
                            LinkedTicketModalSelect::make('parentTicket')
                                ->relationship(
                                    name: 'parentTicket',
                                    titleAttribute: 'subject'
                                )
                                ->tableConfiguration(ParentTicketTable::class)
                                ->label(__('padmission-tickets::tickets.resources.tickets.parent_ticket'))
                                ->placeholder(__('padmission-tickets::tickets.resources.tickets.parent_ticket_placeholder'))
                                ->visible(fn (Ticket $record) => count(TicketPlugin::get($record->panel)->getLinkedTicketParentPanels()) > 0)
                                // Linking again would silently drop the escalation this ticket already has.
                                ->disabled(fn (Ticket $record) => ! static::canEdit($record) || filled($record->linked_ticket_id))
                                ->afterStateUpdated(function (Ticket $record, $state, LinkedTicketModalSelect $component) {
                                    $refusal = match (true) {
                                        filled($record->linked_ticket_id) && $state != $record->linked_ticket_id => __('padmission-tickets::tickets.resources.tickets.link_refused.already_escalated', ['id' => $record->linked_ticket_id]),
                                        filled($state) && ! LinkedTicketCandidates::parents(static::ticketQuery(), $record)->whereKey($state)->exists() => __('padmission-tickets::tickets.resources.tickets.link_refused.not_linkable'),
                                        default => null,
                                    };

                                    if ($refusal !== null) {
                                        $component->state($record->linked_ticket_id);
                                        static::refuseLink($refusal);

                                        return;
                                    }

                                    $record->update(['linked_ticket_id' => $state]);
                                }),

                            LinkedTicketModalSelect::make('childTickets')
                                ->relationship(
                                    name: 'childTickets',
                                    titleAttribute: 'subject'
                                )
                                ->tableConfiguration(ChildTicketsTable::class)
                                ->multiple()
                                ->nullable()
                                ->visible(fn (Ticket $record) => count(TicketPlugin::get($record->panel)->getLinkedTicketChildPanels()) > 0)
                                ->disabled(fn (Ticket $record) => ! static::canEdit($record))
                                ->label(__('padmission-tickets::tickets.resources.tickets.child_tickets'))
                                ->placeholder(__('padmission-tickets::tickets.resources.tickets.child_tickets_placeholder'))
                                ->afterStateUpdated(function (Ticket $record, $state, LinkedTicketModalSelect $component) {
                                    // @TODO: Should this be recorded by Activity Log?
                                    $selectedIds = $state === null ? [] : array_values((array) $state);

                                    $linkableCount = LinkedTicketCandidates::children(static::ticketQuery(), $record)->whereKey($selectedIds)->count();

                                    if ($linkableCount < count($selectedIds)) {
                                        $component->state($record->childTickets()->pluck('id')->all());
                                        static::refuseLink(__('padmission-tickets::tickets.resources.tickets.link_refused.not_linkable'));

                                        return;
                                    }

                                    // The originals can sit outside the viewer's own tenant, such as
                                    // in a cross-tenant panel, so writes go through the same scoping.
                                    DB::transaction(function () use ($record, $selectedIds) {
                                        LinkedTicketCandidates::children(static::ticketQuery(), $record)
                                            ->where('linked_ticket_id', $record->getKey())
                                            ->when($selectedIds !== [], fn ($query) => $query->whereKeyNot($selectedIds))
                                            ->update(['linked_ticket_id' => null]);

                                        if ($selectedIds !== []) {
                                            LinkedTicketCandidates::children(static::ticketQuery(), $record)
                                                ->whereKey($selectedIds)
                                                ->update(['linked_ticket_id' => $record->getKey()]);
                                        }
                                    });
                                }),
                        ]),
                ]),
            ]);
    }

    protected static function ticketQuery(): Builder
    {
        return TicketPlugin::resolveModelClass(Ticket::class)::query();
    }

    protected static function refuseLink(string $body): void
    {
        Notification::make()
            ->danger()
            ->title(__('padmission-tickets::tickets.resources.tickets.link_refused.title'))
            ->body($body)
            ->send();
    }

    protected static function describeEscalation(Ticket $record): ?string
    {
        $plugin = TicketPlugin::get($record->panel);
        $team = $plugin->getEscalationTargetName();
        $key = 'padmission-tickets::tickets.resources.tickets.linked_tickets_description';

        if ($record->isNotInCurrentPanel()) {
            return TicketPlugin::teamText("{$key}.escalated_to_you", TicketPlugin::find($record->panel)?->getSupportTeamName());
        }

        return match (true) {
            filled($record->linked_ticket_id) => TicketPlugin::teamText("{$key}.escalated", $team),
            count($plugin->getLinkedTicketParentPanels()) > 0 => TicketPlugin::teamText("{$key}.not_escalated", $team),
            $record->childTickets()->exists() => __("{$key}.escalated_from"),
            default => null,
        };
    }
}
