<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Filament\Infolists\Components\AvatarEntry;
use Padmission\Tickets\Filament\Infolists\Components\SubmitterEntry;
use Padmission\Tickets\Filament\Infolists\FieldHelp;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\RemoveFromEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ViewOriginalConversationAction;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Tables\ChildTicketsTable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

class ViewTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected $listeners = ['refresh' => '$refresh'];

    public ?int $linkedTicketId = null;

    /**
     * @return Collection<int, Ticket>
     */
    public function linkedTickets(): Collection
    {
        /** @var Ticket $record */
        $record = $this->getRecord();
        $user = Filament::auth()->user();

        $tickets = $record->isInCurrentPanel() && $record->childTickets()->exists()
            ? $record->childTickets
            : collect([$record->parentTicket])->filter();

        return $tickets
            ->filter(fn (Ticket $ticket): bool => $user !== null && Gate::forUser($user)->allows('view', $ticket))
            ->values();
    }

    public function showLinked(int $ticketId): void
    {
        $this->linkedTicketId = $this->linkedTickets()->contains(fn (Ticket $ticket): bool => $ticket->getKey() === $ticketId)
            ? $ticketId
            : null;
    }

    public function closeLinked(): void
    {
        $this->linkedTicketId = null;
    }

    protected function linkedTicket(): ?Ticket
    {
        return $this->linkedTicketId === null
            ? null
            : $this->linkedTickets()->first(fn (Ticket $ticket): bool => $ticket->getKey() === $this->linkedTicketId);
    }

    protected function linkedView(): ?string
    {
        return $this->linkedTicket() === null ? null : TicketPlugin::get()->getLinkedConversationView();
    }

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
            ViewOriginalConversationAction::make()
                ->visible(fn (Ticket $record): bool => TicketPlugin::get()->getLinkedConversationView() === TicketPlugin::LINKED_VIEW_MODAL
                    && $record->isInCurrentPanel()
                    && $this->linkedTickets()->isNotEmpty()
                    && $record->childTickets()->exists()),
            Action::make('show-linked')
                ->label(fn (): string => $this->showLinkedLabel())
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('gray')
                ->visible(fn (): bool => TicketPlugin::get()->getLinkedConversationView() !== TicketPlugin::LINKED_VIEW_MODAL
                    && $this->linkedTickets()->isNotEmpty())
                ->action(fn () => $this->linkedTicketId === null
                    ? $this->showLinked($this->linkedTickets()->first()->getKey())
                    : $this->closeLinked()),
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
            ->columns(['lg' => 12])
            ->components([
                // The heading stays put whether or not a linked ticket is open, for the same reason.
                Section::make()
                    ->heading(fn (): ?string => $this->linkedTickets()->isEmpty() ? null : __('padmission-tickets::tickets.linked_view.replying_on', [
                        'id' => $this->getRecord()->getKey(),
                    ]))
                    ->columnSpan(fn (): array => ['lg' => match ($this->linkedView()) {
                        TicketPlugin::LINKED_VIEW_BESIDE => 7,
                        TicketPlugin::LINKED_VIEW_DRAWER => 12,
                        default => 8,
                    }])
                    ->extraAttributes(['class' => 'pad-ti-chat-section'])
                    ->schema([
                        Section::make(fn (): string => $this->pinnedLinkedHeading())
                            ->collapsible()
                            ->collapsed()
                            ->compact()
                            ->extraAttributes(['class' => 'pad-ti-pinned-linked'])
                            ->visible(fn (): bool => TicketPlugin::get()->shouldPinLinkedConversation()
                                && $this->linkedView() === null
                                && $this->linkedTickets()->isNotEmpty())
                            ->schema([
                                View::make('padmission-tickets::filament.original-conversation')
                                    ->viewData(fn (): array => [
                                        'escalatedTicket' => $this->getRecord(),
                                        'originalTickets' => $this->linkedTickets()->take(1),
                                        'activityService' => resolve(TicketActivityService::class),
                                        'titleRow' => 'status',
                                    ]),
                            ]),

                        ViewEntry::make('chat')->view('padmission-tickets::filament.infolists.chat'),
                    ]),

                // Rendered after the chat and moved into place with CSS order, so opening
                // it never re-creates the chat component and loses a draft reply.
                Group::make([
                    View::make('padmission-tickets::filament.linked-conversation')
                        ->viewData(fn (): array => $this->linkedViewData(drawer: false)),
                ])
                    ->visible(fn (): bool => $this->linkedView() === TicketPlugin::LINKED_VIEW_BESIDE)
                    ->columnSpan(['lg' => 5])
                    ->extraAttributes(['class' => 'pad-ti-linked-col']),

                View::make('padmission-tickets::filament.linked-conversation')
                    ->viewData(fn (): array => $this->linkedViewData(drawer: true))
                    ->visible(fn (): bool => $this->linkedView() === TicketPlugin::LINKED_VIEW_DRAWER)
                    ->columnSpanFull(),

                Grid::make()->columnSpan(['lg' => 4])->columns(1)->hidden(fn (): bool => $this->linkedView() !== null)->schema([
                    Section::make()->columns(2)->extraAttributes(['class' => 'pad-ti-details'])->schema([
                        Actions::make([
                            Action::make('field-help')
                                ->label(__('padmission-tickets::tickets.resources.tickets.field_help.label'))
                                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                                ->link()
                                ->size('sm')
                                ->color('gray')
                                ->slideOver()
                                ->modalWidth(Width::Medium)
                                ->modalHeading(__('padmission-tickets::tickets.resources.tickets.field_help.heading'))
                                ->modalContent(view('padmission-tickets::filament.field-help'))
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel(__('padmission-tickets::tickets.actions.view_original_conversation.close')),
                        ])
                            ->key('fieldHelp')
                            ->alignEnd()
                            ->columnSpanFull()
                            ->visible(fn (): bool => TicketPlugin::get()->getFieldHelp() === TicketPlugin::FIELD_HELP_SUMMARY),

                        FieldHelp::apply(
                            TextEntry::make('status.display_name'),
                            __('padmission-tickets::tickets.resources.tickets.status'),
                            __('padmission-tickets::tickets.resources.tickets.hints.status'),
                        )
                            ->badge()
                            ->color(fn (Ticket $record) => $record->status->colorPalette),

                        FieldHelp::apply(
                            TextEntry::make('priority.display_name'),
                            __('padmission-tickets::tickets.resources.tickets.priority'),
                            __('padmission-tickets::tickets.resources.tickets.hints.priority'),
                        )
                            ->badge()
                            ->color(fn (Ticket $record) => $record->priority->colorPalette),

                        FieldHelp::apply(
                            TextEntry::make('disposition.display_name'),
                            __('padmission-tickets::tickets.resources.tickets.disposition'),
                            __('padmission-tickets::tickets.resources.tickets.hints.disposition'),
                        )
                            ->badge()
                            ->color(fn (Ticket $record) => $record->disposition?->colorPalette)
                            ->hidden(fn (Ticket $record) => ! $record->disposition_id),

                        ...TicketPlugin::get()->getAdditionalTicketDetails(),

                        FieldHelp::apply(
                            SubmitterEntry::make('submitter'),
                            fn (Ticket $record): string => static::isEscalatedHere($record)
                                ? __('padmission-tickets::tickets.resources.tickets.escalated_by')
                                : __('padmission-tickets::tickets.resources.tickets.submitter'),
                            fn (Ticket $record): string => static::isEscalatedHere($record)
                                ? __('padmission-tickets::tickets.resources.tickets.hints.escalated_by')
                                : __('padmission-tickets::tickets.resources.tickets.hints.submitter'),
                        )
                            ->columnSpanFull(),

                        FieldHelp::apply(
                            AvatarEntry::make('assignee'),
                            __('padmission-tickets::tickets.resources.tickets.assignee'),
                            __('padmission-tickets::tickets.resources.tickets.hints.assignee'),
                        )
                            ->hintAction(
                                ReassignTicketAction::make()
                                    ->label(fn (Ticket $record): string => $record->assignee_id
                                        ? __('padmission-tickets::tickets.actions.reassign.inline_label')
                                        : __('padmission-tickets::tickets.actions.reassign.label_unassigned'))
                                    ->icon(null)
                                    ->color('primary')
                                    ->link()
                                    ->size('sm')
                                    ->authorize(static::canEdit(...)),
                            )
                            ->columnSpanFull(),

                        FieldHelp::apply(
                            TextEntry::make('turn'),
                            __('padmission-tickets::tickets.resources.tickets.turn'),
                            __('padmission-tickets::tickets.resources.tickets.hints.turn'),
                        )
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
                        ->heading(fn (Ticket $record): string|Htmlable => FieldHelp::style() === TicketPlugin::FIELD_HELP_TOOLTIP
                            ? FieldHelp::label(
                                __('padmission-tickets::tickets.resources.tickets.linked_tickets'),
                                TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.linked_tickets_help', TicketPlugin::get($record->panel)->getEscalationTargetName() ?? TicketPlugin::find($record->panel)?->getSupportTeamName()),
                            )
                            : __('padmission-tickets::tickets.resources.tickets.linked_tickets'))
                        ->description(fn (Ticket $record): ?string => static::describeEscalation($record))
                        ->visible(fn (Ticket $record) => TicketPlugin::get($record->panel)->hasLinkedTickets())
                        ->compact()
                        ->schema([
                            Text::make(fn (Ticket $record): Htmlable => static::describeMembership($record))
                                ->visible(fn (Ticket $record): bool => $record->isInCurrentPanel() && filled($record->linked_ticket_id)),

                            Actions::make([
                                CreateLinkedTicketAction::make()->authorize(static::canEdit(...)),
                                AddToEscalationAction::make()->authorize(static::canEdit(...)),
                                RemoveFromEscalationAction::make()->authorize(static::canEdit(...)),
                            ])
                                ->key('escalationActions')
                                ->fullWidth(),

                            Text::make(fn (): string => TicketPlugin::teamText(
                                'padmission-tickets::tickets.actions.add_to_escalation.help',
                                TicketPlugin::get()->getEscalationTargetName(),
                            ))
                                ->color('gray')
                                ->visible(fn (Ticket $record): bool => CreateLinkedTicketAction::isAvailableFor($record) && static::canEdit($record)),

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
                                    $selectedIds = $state === null ? [] : array_values((array) $state);

                                    if (! resolve(TicketEscalationLinks::class)->syncOriginals($record, $selectedIds)) {
                                        $component->state($record->childTickets()->pluck($record->qualifyColumn('id'))->all());
                                        static::refuseLink(__('padmission-tickets::tickets.resources.tickets.link_refused.not_linkable'));
                                    }
                                }),
                        ]),
                ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function linkedViewData(bool $drawer): array
    {
        return [
            'record' => $this->getRecord(),
            'linked' => $this->linkedTicket(),
            'linkedTickets' => $this->linkedTickets(),
            'drawer' => $drawer,
            'activityService' => resolve(TicketActivityService::class),
        ];
    }

    protected function showLinkedLabel(): string
    {
        $tickets = $this->linkedTickets();
        $key = 'padmission-tickets::tickets.linked_view.'.(TicketPlugin::get()->getLinkedConversationView() === TicketPlugin::LINKED_VIEW_DRAWER ? 'open' : 'show_beside');

        if ($this->linkedTicketId !== null) {
            return __('padmission-tickets::tickets.linked_view.hide', ['id' => $this->linkedTicketId]);
        }

        return $tickets->count() > 1
            ? __("{$key}_many", ['count' => $tickets->count()])
            : __($key, ['id' => $tickets->first()?->getKey()]);
    }

    protected function pinnedLinkedHeading(): string
    {
        $linked = $this->linkedTickets()->first();

        return $linked === null ? '' : __('padmission-tickets::tickets.linked_view.pinned', [
            'id' => $linked->getKey(),
            'subject' => strip_tags($linked->subject),
        ]);
    }

    protected static function describeMembership(Ticket $record): Htmlable
    {
        $escalationId = $record->linked_ticket_id;
        $others = resolve(TicketEscalationLinks::class)->linkedOriginalsQuery($escalationId)
            ->whereKeyNot($record->getKey())
            ->count();

        $link = new HtmlString(sprintf(
            '<a href="%s" class="pad-ti-link">#%s</a>',
            e(TicketResource::getUrl('view', ['record' => $escalationId])),
            e($escalationId),
        ));

        $team = TicketPlugin::get($record->panel)->getEscalationTargetName();
        $key = 'padmission-tickets::tickets.resources.tickets.membership'.($team === null ? '' : '_to');

        return new HtmlString(trans_choice($key, $others, ['link' => $link, 'count' => $others, 'team' => e($team)]));
    }

    protected static function isEscalatedHere(Ticket $record): bool
    {
        return $record->isInCurrentPanel()
            && count(TicketPlugin::get($record->panel)->getLinkedTicketChildPanels()) > 0
            && $record->childTickets()->exists();
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
        $key = 'padmission-tickets::tickets.resources.tickets.linked_tickets_description';

        if ($record->isNotInCurrentPanel()) {
            return TicketPlugin::teamText("{$key}.escalated_to_you", TicketPlugin::find($record->panel)?->getSupportTeamName());
        }

        // The organization side needs no sentence: the membership line or the two
        // escalate choices already say where the ticket stands.
        return filled($record->linked_ticket_id) || $record->childTickets()->doesntExist()
            ? null
            : __($key.(TicketPlugin::get()->getLinkedConversationView() === TicketPlugin::LINKED_VIEW_MODAL ? '.escalated_from' : '.escalated_from_beside'));
    }
}
