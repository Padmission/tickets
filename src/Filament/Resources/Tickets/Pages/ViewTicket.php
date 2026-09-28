<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Filament\Infolists\Components\AvatarEntry;
use Padmission\Tickets\Filament\Infolists\Components\SubmitterEntry;
use Padmission\Tickets\Filament\Infolists\FieldHelp;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\DeleteTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\HandOverEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\RemoveFromEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReopenTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ViewOriginalConversationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\Concerns\ExplainsStaleEscalationActions;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Tables\ChildTicketsTable;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Services\EscalationSummary;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAssignee;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Support\ConversationState;
use Padmission\Tickets\TicketPlugin;

class ViewTicket extends EditRecord
{
    use ExplainsStaleEscalationActions;

    protected const int PERSON_ACTION_SLOTS = 3;

    protected static string $resource = TicketResource::class;

    protected $listeners = ['refresh' => '$refresh'];

    #[Url(as: 'linked')]
    public ?int $linkedTicketId = null;

    /*
     * Read once per request, because it is one costly query that the badges,
     * the status line and the pane all share.
     */
    protected ?ConversationState $conversationState = null;

    /** @var Collection<int, Ticket>|null */
    protected ?Collection $linkedTickets = null;

    protected ?bool $hasOriginals = null;

    /** @var array{text: string, warning: bool, readReplyLabel: ?string, escalationId: ?int, openUrl: ?string, takeOver: bool}|false|null */
    protected array|false|null $escalationStatus = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->usesPane()) {
            $this->linkedTicketId = null;

            return;
        }

        $requested = $this->linkedTicketId ?? $this->linkedTicketToOpen()?->getKey();

        if ($requested === null) {
            $this->closeLinked();

            return;
        }

        $this->showLinked($requested);
    }

    /*
     * Where the viewer stopped reading the pane's ticket, taken before it is
     * marked read, so the pane can open at the first message they missed.
     */
    protected ?int $linkedLastSeenId = null;

    public function rendering(): void
    {
        $linked = $this->linkedTicket();
        $viewer = Filament::auth()->user();

        $this->linkedLastSeenId = $linked === null || $viewer === null
            ? null
            : resolve(TicketActivityService::class)->getUserState($linked, $viewer)?->last_seen_activity_id;

        $this->markLinkedEscalationSeen();
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function linkedTickets(): Collection
    {
        /** @var Ticket $record */
        $record = $this->getRecord();
        $user = Filament::auth()->user();

        if (! $this->canSeeEscalation($record)) {
            return collect();
        }

        return $this->linkedTickets ??= ($this->hasOriginals()
            ? $this->originals()
            : collect([$record->parentTicket])->filter())
            // The team an escalation was sent to reads every original beside it, as authorizeAccess() lets it.
            ->filter(fn (Ticket $ticket): bool => $this->isEscalatedHere($record) || ($user !== null && Gate::forUser($user)->allows('view', $ticket)))
            ->values();
    }

    /*
     * A panel that reads originals in a modal has no pane, so nothing is
     * shown, or marked as read, here.
     */
    public function showLinked(int $ticketId): void
    {
        $this->escalationStatus = null;

        $this->linkedTicketId = $this->usesPane() && $this->linkedTickets()->contains(fn (Ticket $ticket): bool => $ticket->getKey() === $ticketId)
            ? $ticketId
            : null;
    }

    public function closeLinked(): void
    {
        $this->escalationStatus = null;
        $this->linkedTicketId = null;
    }

    protected function usesPane(): bool
    {
        return TicketPlugin::get()->getLinkedConversationView() !== TicketPlugin::LINKED_VIEW_MODAL;
    }

    /*
     * Links change through the Escalation actions and the originals picker,
     * which run before the page renders again in the same request.
     */
    protected function forgetLinks(): void
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        $record->unsetRelation('childTickets')->unsetRelation('parentTicket');

        $this->linkedTickets = null;
        $this->hasOriginals = null;
        $this->escalationStatus = null;
        $this->escalations = [];
        $this->conversationState = null;
        $this->editable = [];
    }

    /*
     * Hand over, Take over and Close escalation change the escalation, whose
     * owner and state every memo above was read from. A refresh drops the
     * record's relations, so getRecord() finds another panel's assignee again.
     */
    protected function afterActionCalled(Action $action): void
    {
        $this->forgetLinks();

        /** @var Ticket $record */
        $record = $this->getRecord();

        if ($action instanceof HandOverEscalationAction || $action instanceof CloseEscalationAction) {
            $record->refresh();
        }

        // The chat keeps its own state, so it is told when an action closed or reopened the
        // ticket, changed who may reply or wrote to the requester. Whether a closed ticket
        // still takes a reply that reopens it, the chat asks the server.
        $this->dispatch('ticket-chat-changed', ticketId: $record->getKey(), canReply: resolve(TicketAuth::class)->canReply($record, Filament::auth()->user()));
    }

    /*
     * Rendered in the status line when the other team replied to a colleague.
     */
    public function takeOverAction(): Action
    {
        return HandOverEscalationAction::make('takeOver')
            ->record(fn (): Model => $this->getRecord())
            ->escalationUsing(fn (Ticket $record): ?Ticket => $this->escalationOf($record))
            ->hidden(fn (Ticket $record): bool => $record->isClosed || ! $this->canSeeEscalation($record))
            ->size('sm');
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

    /*
     * Opened without being asked for when the page exists to act on it: the
     * owner of an escalation with a reply to pass on, and the team an
     * escalation was sent to, which reads the original beside it.
     */
    protected function linkedTicketToOpen(): ?Ticket
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        if ($this->isEscalatedHere($record)) {
            return $this->linkedTickets()->first();
        }

        if ($record->isNotInCurrentPanel() || $this->isShowingOriginals()) {
            return null;
        }

        $state = $this->conversationState();

        return $state->marker === 'replied' && $state->ownerIsViewer()
            ? $this->linkedTickets()->first()
            : null;
    }

    /*
     * The owner reads the other team's messages in the pane, so they no
     * longer count as new on the escalation.
     */
    protected function markLinkedEscalationSeen(): void
    {
        $escalation = $this->linkedTicket();
        $viewer = Filament::auth()->user();

        if ($escalation === null || $viewer === null || $this->isShowingOriginals() || ! $escalation->isSubmittedBy($viewer)) {
            return;
        }

        $service = resolve(TicketActivityService::class);

        $newest = $escalation->ticketActivities()
            ->whereIn('type', $service->getActivityTypesForSender($escalation, ActivitySender::User, $viewer))
            ->max('id');

        if ($newest !== null) {
            $service->markAsSeen($escalation, $viewer, (int) $newest);
        }
    }

    protected function conversationState(): ConversationState
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        return $this->conversationState ??= ConversationState::for($record);
    }

    /*
     * Another panel's ticket is opened here only by its submitter. The team
     * an original was escalated to reads it beside its own escalation, where
     * it cannot write to the requester.
     */
    protected function authorizeAccess(): void
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        if ($record->isNotInCurrentPanel() && ! $record->isSubmittedBy(Filament::auth()->id())) {
            $escalation = resolve(TicketEscalationLinks::class)->escalationOf($record);

            abort_unless($escalation?->isInCurrentPanel() === true && static::getResource()::canView($escalation), 403);

            throw new HttpResponseException(new RedirectResponse(
                static::getResource()::getUrl('view', ['record' => $escalation, 'linked' => $record->getKey()]),
            ));
        }

        abort_unless(static::getResource()::canView($record), 403);
    }

    /*
     * A requester who may browse tickets can open their own original, but
     * whether and where it was escalated is the organization's business.
     */
    public function canSeeEscalation(Ticket $record): bool
    {
        return $record->isNotInCurrentPanel() || $this->canEdit($record);
    }

    /**
     * Asked by several sections and action visibilities on each render.
     *
     * @var array<string, bool>
     */
    protected array $editable = [];

    protected function canEdit(?Ticket $record): bool
    {
        if ($record === null) {
            return static::getResource()::canEdit($record);
        }

        return $this->editable[(string) $record->getKey()] ??= static::getResource()::canEdit($record);
    }

    /*
     * Actions ask the Gate on every call, never the memo: read-only
     * impersonation tells a write from the abilities an action's
     * authorization asks, which a remembered answer would hide.
     */
    protected function authorizesEdit(?Ticket $record): bool
    {
        return static::getResource()::canEdit($record);
    }

    /**
     * Found once per request and assignee, so a lookup that finds nobody is
     * not repeated on every call.
     *
     * @var array<string, ?Model>
     */
    protected array $foreignAssignees = [];

    /*
     * Another panel's ticket is assigned to someone only that panel's scopes
     * may reveal. Relations are not kept between requests, and refresh() or
     * load() reloads it through this panel's scopes, so it is set again then.
     */
    public function getRecord(): Model
    {
        $record = parent::getRecord();

        if (! $record instanceof Ticket || $record->isInCurrentPanel()) {
            return $record;
        }

        $needsAssignee = ! $record->relationLoaded('assignee')
            || ($record->getRelation('assignee') === null && filled($record->assignee_id));

        if ($needsAssignee) {
            $key = (string) $record->assignee_id;

            if (! array_key_exists($key, $this->foreignAssignees)) {
                $this->foreignAssignees[$key] = TicketAssignee::for($record);
            }

            $record->setRelation('assignee', $this->foreignAssignees[$key]);
        }

        $needsDisposition = filled($record->disposition_id)
            && (! $record->relationLoaded('disposition') || $record->getRelation('disposition') === null);

        if ($needsDisposition) {
            $key = (string) $record->disposition_id;

            if (! array_key_exists($key, $this->foreignDispositions)) {
                $this->foreignDispositions[$key] = $this->findForeignDisposition($record);
            }

            $record->setRelation('disposition', $this->foreignDispositions[$key]);
        }

        return $record;
    }

    /*
     * Another panel's disposition, such as the one the other team closed an
     * escalation with, is found through that panel's scopes like its assignee.
     */
    /** @var array<string, ?Model> */
    protected array $foreignDispositions = [];

    protected function findForeignDisposition(Ticket $record): ?Model
    {
        /** @var Builder<TicketDisposition> $query */
        $query = $record->disposition()->getRelated()->newQuery();
        $query->withTrashed()->withoutGlobalScope(CurrentPanelScope::class);
        $modifier = TicketPlugin::find($record->panel)?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'disposition']);
        }

        return $query->find($record->disposition_id);
    }

    public function getBreadcrumb(): string
    {
        return 'View';
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        return filled($record->subject) ? $record->subject : __('padmission-tickets::tickets.resources.tickets.view_title');
    }

    public function getHeading(): string|Htmlable
    {
        /**
         * @var Ticket $ticket
         */
        $ticket = $this->record;

        return (string) $ticket->subject;
    }

    /*
     * The number is a reference to quote (it is in every email subject), not
     * something to read, so it sits once, small, under the heading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        /** @var Ticket $record */
        $record = $this->getRecord();
        $id = $record->getKey();
        $requester = $record->requesterName();

        $text = match (true) {
            $record->isEscalation() => TicketPlugin::teamText('padmission-tickets::tickets.subheading.escalation', TicketPlugin::find($record->panel)?->getSupportTeamName(), ['id' => $id]),
            filled($requester) && ! $record->isSubmittedBy(Filament::auth()->id()) => __('padmission-tickets::tickets.subheading.original', ['name' => $requester, 'id' => $id]),
            default => __('padmission-tickets::tickets.ticket_number', ['id' => $id]),
        };

        return new HtmlString(sprintf('<span class="pad-ti-ticket-number">%s</span>', e($text)));
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewOriginalConversationAction::make()
                ->visible(fn (Ticket $record): bool => TicketPlugin::get()->getLinkedConversationView() === TicketPlugin::LINKED_VIEW_MODAL
                    && $this->canSeeEscalation($record)
                    && $record->isInCurrentPanel()
                    && $this->linkedTickets()->isNotEmpty()
                    && $this->hasOriginals()),
            Action::make('show-linked')
                ->label(fn (): string => $this->showLinkedLabel())
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('gray')
                ->visible(fn (): bool => TicketPlugin::get()->getLinkedConversationView() !== TicketPlugin::LINKED_VIEW_MODAL
                    && $this->linkedTickets()->isNotEmpty())
                ->action(fn () => $this->linkedTicketId === null
                    ? $this->showLinked($this->linkedTickets()->first()->getKey())
                    : $this->closeLinked()),
            ReopenTicketAction::make(),
            CloseTicketAction::make()->authorize($this->authorizesEdit(...)),
            CloseEscalationAction::make(),
            EditTicketAction::make()->authorize($this->authorizesEdit(...)),
            ActionGroup::make([
                DeleteTicketAction::make(),
            ]),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    #[On('message-sent')]
    public function rerenderAfterMessage(bool $reopened = false)
    {
        $this->forgetLinks();

        // A reply that reopened the ticket changes its header and sidebar, so all of it is drawn again.
        if ($reopened) {
            return;
        }

        $originals = $this->getSchemaComponent('form.childTickets');

        if ($originals instanceof LinkedTicketModalSelect) {
            $originals->forgetRelayPending();
        }

        /** @var Ticket $record */
        $record = $this->getRecord();

        $this->conversationState = ConversationState::for($record);

        $this->skipRender();
        $this->partiallyRenderSchemaComponent('form.turn');
        $this->partiallyRenderSchemaComponent('form.latestMessage.created_at');
        $this->partiallyRenderSchemaComponent('form.chatTurn');
        $this->partiallyRenderSchemaComponent('form.escalationStatus');
        // A reply on either side can settle whether a requester still waits to hear the other team's answer.
        $this->partiallyRenderSchemaComponent('form.escalation');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 12])
            ->components([
                // The heading stays put whether or not a linked ticket is open, for the same reason.
                Section::make()
                    ->heading(fn (): ?string => $this->chatHeading())
                    ->description(fn (): ?string => $this->chatDescription())
                    // The details column, and the Waiting on in it, is hidden while the pane is open.
                    ->afterHeader([
                        Text::make(fn (): string => __('padmission-tickets::tickets.resources.tickets.waiting_on_pill', ['label' => $this->conversationState()->label()]))
                            ->key('chatTurn')
                            ->badge()
                            ->color(fn (): string => $this->conversationState()->color())
                            ->icon(fn (): ?string => $this->conversationState()->icon())
                            ->tooltip(fn (): ?string => $this->conversationState()->tooltip())
                            ->visible(fn (): bool => $this->linkedView() !== null && $this->conversationState()->label() !== null),
                    ])
                    ->columnSpan(fn (): array => ['lg' => match ($this->linkedView()) {
                        TicketPlugin::LINKED_VIEW_BESIDE => 7,
                        TicketPlugin::LINKED_VIEW_DRAWER => 12,
                        default => 8,
                    }])
                    ->extraAttributes(['class' => 'pad-ti-chat-section'])
                    ->schema([
                        View::make('padmission-tickets::filament.escalation-status')
                            ->key('escalationStatus')
                            ->viewData(fn (): array => ['status' => $this->escalationStatus()])
                            ->visible(fn (): bool => $this->escalationStatus() !== null),

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

                        ViewEntry::make('chat')
                            ->view('padmission-tickets::filament.infolists.chat')
                            ->viewData(fn (Ticket $record): array => [
                                'placeholder' => $this->chatPlaceholder(),
                                'closedEmptyMessage' => $record->isClosed
                                    ? __('padmission-tickets::chat.chat.closed_empty', ['time' => $record->closed_at?->diffForHumans()])
                                    : null,
                            ]),
                    ]),

                // Rendered after the chat and moved into place with CSS order, so opening
                // it never re-creates the chat component and loses a draft reply.
                Group::make([
                    View::make('padmission-tickets::filament.linked-conversation')
                        ->viewData(fn (): array => $this->linkedViewData(drawer: false))
                        ->schema([
                            Actions::make(fn (): array => $this->linkedRequesterActions())->key('linkedRequesterActions'),
                        ]),
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
                                ->modalContent(fn (Ticket $record): ViewContract => view('padmission-tickets::filament.field-help', [
                                    'handledBy' => $this->isEscalatedElsewhere($record),
                                    'contact' => $this->isEscalatedHere($record),
                                ]))
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
                            fn (Ticket $record): string => $this->isEscalatedElsewhere($record)
                                ? TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.hints.status_elsewhere', static::teamOf($record))
                                : __('padmission-tickets::tickets.resources.tickets.hints.status'),
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
                            SubmitterEntry::make('submitter')
                                ->beforeLabel(static::editBesideLabel(HandOverEscalationAction::make()))
                                ->extraEntryWrapperAttributes(['class' => 'pad-ti-edit-entry'])
                                ->hintActions($this->personActionSlots(fn (Ticket $record): ?Model => $record->submitter)),
                            fn (Ticket $record): string => match (true) {
                                $this->isEscalatedHere($record) => __('padmission-tickets::tickets.resources.tickets.contact'),
                                $this->isEscalatedElsewhere($record) => __('padmission-tickets::tickets.resources.tickets.handled_by'),
                                default => __('padmission-tickets::tickets.resources.tickets.submitter'),
                            },
                            fn (Ticket $record): string => match (true) {
                                $this->isEscalatedHere($record) => static::contactHint($record),
                                $this->isEscalatedElsewhere($record) => TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.hints.handled_by', static::teamOf($record)),
                                default => __('padmission-tickets::tickets.resources.tickets.hints.submitter'),
                            },
                        )
                            ->columnSpanFull(),

                        FieldHelp::apply(
                            AvatarEntry::make('assignee'),
                            __('padmission-tickets::tickets.resources.tickets.assignee'),
                            fn (Ticket $record): string => $record->isNotInCurrentPanel() && $record->isEscalation()
                                ? TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.hints.assignee_elsewhere', TicketPlugin::find($record->panel)?->getSupportTeamName())
                                : __('padmission-tickets::tickets.resources.tickets.hints.assignee'),
                        )
                            ->extraEntryWrapperAttributes(['class' => 'pad-ti-edit-entry'])
                            ->beforeLabel(static::editBesideLabel(
                                ReassignTicketAction::make()
                                    ->label(fn (Ticket $record): string => $record->assignee_id
                                        ? __('padmission-tickets::tickets.actions.reassign.inline_label')
                                        : __('padmission-tickets::tickets.actions.reassign.label_unassigned'))
                                    ->authorize($this->authorizesEdit(...)),
                            ))
                            ->columnSpanFull(),

                        FieldHelp::apply(
                            TextEntry::make('turn')
                                ->state(fn (): ?string => $this->conversationState()->label())
                                ->placeholder('–'),
                            __('padmission-tickets::tickets.resources.tickets.turn'),
                            __('padmission-tickets::tickets.resources.tickets.hints.turn'),
                        )
                            ->badge()
                            ->color(fn (): string => $this->conversationState()->color())
                            ->icon(fn (): ?string => $this->conversationState()->icon())
                            ->tooltip(fn (): ?string => $this->conversationState()->tooltip())
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
                            ->tooltip(fn (?CarbonImmutable $state) => TicketPlugin::formatMessageTime($state))
                            ->columnSpanFull(),

                        TextEntry::make('closed_at')
                            ->label(__('padmission-tickets::tickets.resources.tickets.closed_at'))
                            ->visible(fn (Ticket $record) => $record->isClosed)
                            ->dateTime()
                            ->formatStateUsing(fn ($state) => $state?->diffForHumans())
                            ->tooltip(fn ($state) => TicketPlugin::formatMessageTime($state))
                            ->columnSpanFull(),

                    ]),

                    Section::make()
                        ->heading(fn (Ticket $record): string|Htmlable => FieldHelp::style() === TicketPlugin::FIELD_HELP_TOOLTIP
                            ? FieldHelp::label(
                                __('padmission-tickets::tickets.resources.tickets.linked_tickets'),
                                TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.linked_tickets_help', TicketPlugin::get($record->panel)->getEscalationTargetName() ?? TicketPlugin::find($record->panel)?->getSupportTeamName()),
                            )
                            : __('padmission-tickets::tickets.resources.tickets.linked_tickets'))
                        ->key('escalation', isInheritable: false)
                        ->description(fn (Ticket $record): ?string => $this->describeEscalation($record))
                        // A closed ticket that was never escalated has nothing to say or offer here, and a
                        // question asked directly is joined from the original's side, if ever.
                        ->visible(fn (Ticket $record): bool => TicketPlugin::get($record->panel)->hasLinkedTickets()
                            && ($record->isInCurrentPanel() || $this->isEscalatedElsewhere($record))
                            && $this->canSeeEscalation($record)
                            && ! ($record->isClosed && blank($record->linked_ticket_id) && ! $record->isEscalation())
                            && ($this->hasOriginals() || ! $record->isDirectQuestion()))
                        ->compact()
                        ->schema([
                            Text::make(fn (Ticket $record): string => $this->describeMembership($record))
                                ->visible(fn (Ticket $record): bool => $record->isInCurrentPanel() && $this->escalationOf($record) !== null),

                            Actions::make([
                                CreateLinkedTicketAction::make()->authorize($this->authorizesEdit(...)),
                                AddToEscalationAction::make()->authorize($this->authorizesEdit(...)),
                                Action::make('open-escalation')
                                    ->label(__('padmission-tickets::tickets.linked_view.open_escalation'))
                                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                    ->color('gray')
                                    ->url(fn (Ticket $record): ?string => $this->openEscalationUrl($record))
                                    // The status line offers it too when the next step is on the escalation.
                                    ->visible(fn (Ticket $record): bool => $this->escalationOf($record)?->isClosed === false
                                        && $this->openEscalationUrl($record) !== null
                                        && ($this->escalationStatus()['openUrl'] ?? null) === null),
                                HandOverEscalationAction::make('take-over-escalation')
                                    ->escalationUsing(fn (Ticket $record): ?Ticket => $this->escalationOf($record))
                                    ->button()
                                    // The status line offers it too when the other team replied to a colleague.
                                    ->hidden(fn (Ticket $record): bool => $record->isClosed
                                        || $this->openEscalationUrl($record) !== null
                                        || ($this->escalationStatus()['takeOver'] ?? false)),
                                RemoveFromEscalationAction::make()->button()->authorize($this->authorizesEdit(...)),
                            ])
                                ->key('escalationActions')
                                ->extraAttributes(['class' => 'pad-ti-escalation-actions'])
                                ->fullWidth(),

                            Text::make(fn (): string => TicketPlugin::teamText(
                                'padmission-tickets::tickets.actions.add_to_escalation.help',
                                TicketPlugin::get()->getEscalationTargetName(),
                            ))
                                ->color('gray')
                                ->visible(fn (Ticket $record): bool => AddToEscalationAction::isAvailableFor($record) && static::canEdit($record)),

                            LinkedTicketModalSelect::make('childTickets')
                                ->relationship(
                                    name: 'childTickets',
                                    titleAttribute: 'subject'
                                )
                                ->tableConfiguration(ChildTicketsTable::class)
                                ->multiple()
                                ->nullable()
                                ->visible(fn (Ticket $record) => count(TicketPlugin::get($record->panel)->getLinkedTicketChildPanels()) > 0)
                                ->disabled(fn (Ticket $record) => $record->isClosed || ! static::canEdit($record))
                                ->label(__('padmission-tickets::tickets.resources.tickets.child_tickets'))
                                ->placeholder(__('padmission-tickets::tickets.resources.tickets.child_tickets_placeholder'))
                                ->afterStateUpdated(function (Ticket $record, $state, LinkedTicketModalSelect $component) {
                                    $selectedIds = $state === null ? [] : array_values((array) $state);

                                    $synced = resolve(TicketEscalationLinks::class)->syncOriginals($record, $selectedIds);

                                    $this->forgetLinks();

                                    if (! $synced) {
                                        $component->state($record->childTickets()->pluck($record->qualifyColumn('id'))->all());
                                        static::refuseLink(__('padmission-tickets::tickets.resources.tickets.link_refused.not_linkable'));
                                    }
                                }),
                        ]),
                ]),
            ]);
    }

    /*
     * A field that can be changed from the sidebar has a pencil right after
     * its label, named by its tooltip, rather than a link at the far edge
     * where it read as belonging to the host's person actions. Filament's
     * slot after the label holds the hints at that far edge, so the pencil
     * goes in the slot before it and tickets.css draws it after the label.
     */
    protected static function editBesideLabel(Action $action): Action
    {
        return $action
            ->iconButton()
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconSize('sm')
            ->color('gray')
            ->tooltip(fn (Action $action): string => $action->getLabel())
            ->extraAttributes(['class' => 'pad-ti-edit-beside-label']);
    }

    /*
     * Hint actions come one to a closure, so each slot offers one of the
     * host's person actions, styled as the sidebar's Change link.
     *
     * @param  Closure(Ticket): ?Model  $person
     * @return list<Closure(Ticket): ?Action>
     */
    protected function personActionSlots(Closure $person): array
    {
        return array_map(
            fn (int $slot): Closure => fn (Ticket $record): ?Action => $this->styledPersonActions($person($record), $record)[$slot] ?? null,
            range(0, self::PERSON_ACTION_SLOTS - 1),
        );
    }

    /**
     * @return list<Action>
     */
    protected function styledPersonActions(?Model $person, Ticket $ticket): array
    {
        if ($person === null) {
            return [];
        }

        return array_map(
            fn (Action $action): Action => $action->icon(null)->color('primary')->link()->size('sm'),
            TicketPlugin::get()->getPersonActions($person, $ticket),
        );
    }

    /*
     * Beside an escalation, the requester of the original it shows.
     *
     * @return list<Action>
     */
    protected function linkedRequesterActions(): array
    {
        $linked = $this->linkedTicket();

        if ($linked === null || $linked->isEscalation()) {
            return [];
        }

        return $this->styledPersonActions($linked->submitter, $linked);
    }

    /**
     * @return array<string, mixed>
     */
    protected function linkedViewData(bool $drawer): array
    {
        $linked = $this->linkedTicket();

        return [
            'record' => $this->getRecord(),
            'linked' => $linked,
            'linkedTickets' => $this->linkedTickets(),
            'drawer' => $drawer,
            'headings' => $this->linkedTickets()->mapWithKeys(fn (Ticket $ticket): array => [$ticket->getKey() => $this->linkedTicketHeading($ticket)]),
            'headerLink' => $linked === null ? null : $this->linkedHeaderLink($linked),
            'lastSeenId' => $this->linkedLastSeenId,
            'activityService' => resolve(TicketActivityService::class),
        ];
    }

    /*
     * Whether this page is the escalation (its linked tickets are originals)
     * or an original (its linked ticket is the escalation).
     */
    protected function isShowingOriginals(): bool
    {
        return $this->hasOriginals();
    }

    protected function hasOriginals(): bool
    {
        return $this->hasOriginals ??= $this->originals()->isNotEmpty();
    }

    /*
     * Loaded once with what the description, the pane and the original
     * cards read from each, so a long escalation costs no more queries.
     *
     * @return Collection<int, Ticket>
     */
    protected function originals(): Collection
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        return $record->loadMissing(['childTickets.submitter', 'childTickets.status'])->childTickets;
    }

    protected function showLinkedLabel(): string
    {
        $key = 'padmission-tickets::tickets.linked_view.'.($this->linkedTicketId === null ? 'show' : 'hide');

        return $this->isShowingOriginals()
            ? trans_choice("{$key}_originals", $this->linkedTickets()->count())
            : __("{$key}_escalation");
    }

    /*
     * The escalation's owner answers each side in its own conversation, so
     * the pane links to the other one. The team the escalation was sent to
     * has nowhere else to go.
     *
     * @return array{label: string, url: string}|null
     */
    protected function linkedHeaderLink(Ticket $linked): ?array
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        if ($this->isEscalatedHere($record) || ! static::getResource()::canView($linked)) {
            return null;
        }

        if (! $this->isShowingOriginals()) {
            return [
                'label' => __('padmission-tickets::tickets.linked_view.open_escalation'),
                'url' => static::getResource()::getUrl('view', ['record' => $linked, 'linked' => $record->getKey()]),
            ];
        }

        if ($linked->isNotInCurrentPanel()) {
            return null;
        }

        $requester = $linked->requesterName();

        return [
            'label' => filled($requester)
                ? __('padmission-tickets::tickets.linked_view.answer_requester', ['name' => $requester])
                : __('padmission-tickets::tickets.linked_view.answer_requester_unnamed'),
            'url' => static::getResource()::getUrl('view', ['record' => $linked, 'linked' => $record->getKey()]),
        ];
    }

    protected function chatHeading(): ?string
    {
        /** @var Ticket $record */
        $record = $this->getRecord();
        $key = 'padmission-tickets::tickets.linked_view.';

        if ($this->isEscalatedElsewhere($record)) {
            return TicketPlugin::teamText($key.'conversation_with_team', static::teamOf($record));
        }

        if ($record->isInCurrentPanel() && $record->isEscalation()) {
            $contact = $record->requesterName();
            $organization = $this->organizationOf($record);

            return match (true) {
                blank($contact) => null,
                blank($organization) => __($key.'conversation_with_contact_unnamed_org', ['name' => $contact]),
                default => __($key.'conversation_with_contact', ['name' => $contact, 'organization' => $organization]),
            };
        }

        if ($record->isSubmittedBy(Filament::auth()->id())) {
            return __($key.'conversation_own');
        }

        $requester = $record->requesterName();

        return filled($requester) ? __($key.'conversation_with', ['name' => $requester]) : null;
    }

    /*
     * An escalation is a conversation about other people's tickets, and they
     * never see it, which is easy to forget while writing in it.
     */
    protected function chatDescription(): ?string
    {
        /** @var Ticket $record */
        $record = $this->getRecord();
        $key = 'padmission-tickets::tickets.linked_view.';

        if (! $record->isEscalation()) {
            return null;
        }

        $originals = $this->originals();

        if ($originals->isEmpty() && $record->isDirectQuestion()) {
            return $record->isSubmittedBy(Filament::auth()->id())
                ? __($key.'conversation_about_question_own')
                : __($key.'conversation_about_question', ['name' => $record->requesterName() ?? __('padmission-tickets::tickets.actions.close.the_contact')]);
        }

        if ($originals->isEmpty()) {
            return __($key.'conversation_about_none');
        }

        $requester = EscalationSummary::soleRequester($originals);
        $replace = [
            'originals' => EscalationSummary::originals($originals),
            'name' => $requester,
            'contact' => $record->requesterName() ?? __('padmission-tickets::tickets.actions.close.the_contact'),
        ];
        $received = $record->isInCurrentPanel() ? 'received_' : '';

        return filled($requester)
            ? __($key.'conversation_about_'.$received.'one', $replace)
            : trans_choice($key.'conversation_about_'.$received.'many', $originals->count(), $replace);
    }

    protected function chatPlaceholder(): ?string
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        $name = match (true) {
            $this->isEscalatedElsewhere($record) => static::teamOf($record),
            $record->isSubmittedBy(Filament::auth()->id()) => null,
            default => $record->requesterName(),
        };

        return filled($name) ? __('padmission-tickets::chat.chat.placeholder_reply_to', ['name' => $name]) : null;
    }

    /*
     * On an escalated original, where the other conversation stands and what
     * the viewer can do about it, since the list marker is only a word.
     *
     * @return array{text: string, warning: bool, readReplyLabel: ?string, escalationId: ?int, openUrl: ?string, takeOver: bool}|null
     */
    protected function escalationStatus(): ?array
    {
        $this->escalationStatus ??= $this->readEscalationStatus() ?? false;

        return $this->escalationStatus ?: null;
    }

    /**
     * @return array{text: string, warning: bool, readReplyLabel: ?string, escalationId: ?int, openUrl: ?string, takeOver: bool}|null
     */
    protected function readEscalationStatus(): ?array
    {
        /** @var Ticket $record */
        $record = $this->getRecord();

        if ($record->isNotInCurrentPanel() || $record->isClosed || ! $this->canSeeEscalation($record)) {
            return null;
        }

        $escalation = $this->escalationOf($record);

        if ($escalation === null) {
            return null;
        }

        $key = 'padmission-tickets::tickets.escalation_status.';
        $team = static::teamOf($escalation);
        $isOwner = $escalation->isSubmittedBy(Filament::auth()->id());
        $handler = $escalation->submitter === null ? null : resolve(GetUserDisplayName::class)->forUser($escalation->submitter);
        $requester = $record->requesterName() ?? __('padmission-tickets::tickets.resources.tickets.the_requester');
        $canRead = $this->usesPane() && $this->linkedTickets()->contains(fn (Ticket $ticket): bool => $ticket->is($escalation));
        $handlerSuffix = $isOwner ? '_you' : ($handler === null ? '_unnamed' : '');

        $case = match (true) {
            $this->conversationState()->marker === 'replied' => $isOwner ? 'replied_you' : 'replied_other',
            $escalation->isClosed => 'closed',
            $escalation->turn === Turn::User => 'waiting_owner',
            default => 'waiting_team',
        };

        $closed = blank($escalation->closed_by)
            ? __($key.'closed_unknown', ['time' => $escalation->closed_at?->diffForHumans()])
            : __($key.'closed', [
                'closer' => resolve(GetUserDisplayName::class)($escalation->closed_by, $escalation->panel),
                'time' => $escalation->closed_at?->diffForHumans(),
            ]);

        // A reply that came before the escalation closed can no longer be answered there.
        $text = match (true) {
            $case === 'closed' => $closed,
            $escalation->isClosed && $case === 'replied_you' => $closed.' '.TicketPlugin::teamText($key.'replied_closed_you', $team, ['name' => $requester]),
            $escalation->isClosed => $closed.' '.TicketPlugin::teamText($key.'replied_closed_other'.($handler === null ? '_unnamed' : ''), $team, ['handler' => $handler]),
            $case === 'replied_you' => TicketPlugin::teamText($key.'replied_you', $team, ['name' => $requester]),
            $case === 'replied_other' => TicketPlugin::teamText($key.'replied_other'.($handler === null ? '_unnamed' : ''), $team, ['handler' => $handler]),
            default => TicketPlugin::teamText($key.$case.$handlerSuffix, $team, ['handler' => $handler]),
        };

        if ($case !== 'closed' && $this->requesterIsWaiting($record)) {
            $text .= ' '.__($key.'requester_waiting', ['name' => $requester]);
        }

        $paneOpen = $this->linkedTicket()?->is($escalation) === true;
        // With the escalation already beside the chat, the reply is right there.
        $readReply = $case === 'replied_you' && $canRead && ! $paneOpen;
        $takeOver = $case === 'replied_other' && HandOverEscalationAction::isAvailableFor($escalation);

        return [
            'text' => $text,
            'warning' => $case === 'replied_you',
            'readReplyLabel' => $readReply ? TicketPlugin::teamText($key.'read_reply', $team) : null,
            'escalationId' => $readReply ? (int) $escalation->getKey() : null,
            // The pane has its own link to the escalation.
            'openUrl' => in_array($case, ['replied_you', 'waiting_owner'], true) && ! $paneOpen ? $this->openEscalationUrl($record) : null,
            'takeOver' => $takeOver,
        ];
    }

    protected function requesterIsWaiting(Ticket $record): bool
    {
        $latest = $record->ticketActivities()
            ->where('type', ActivityType::Message)
            ->selectRaw('max(case when sender = ? then id end) as requester, max(case when sender = ? then id end) as organization', [
                ActivitySender::User->value,
                ActivitySender::Supporter->value,
            ])
            ->toBase()
            ->first();

        return (int) ($latest->requester ?? 0) > (int) ($latest->organization ?? 0);
    }

    /**
     * Keyed by link, so removing or moving the link during the request is seen.
     *
     * @var array<int|string, ?Ticket>
     */
    protected array $escalations = [];

    protected function escalationOf(Ticket $record): ?Ticket
    {
        $link = $record->linked_ticket_id;

        if (blank($link)) {
            return null;
        }

        if (! array_key_exists($link, $this->escalations)) {
            $this->escalations[$link] = resolve(TicketEscalationLinks::class)->escalationOf($record);
        }

        return $this->escalations[$link];
    }

    protected function openEscalationUrl(Ticket $record): ?string
    {
        $escalation = $this->escalationOf($record);

        if ($escalation === null || ! static::getResource()::canView($escalation)) {
            return null;
        }

        return static::getResource()::getUrl('view', ['record' => $escalation, 'linked' => $record->getKey()]);
    }

    protected function linkedTicketHeading(Ticket $linked): string
    {
        $key = 'padmission-tickets::tickets.linked_view.';

        if ($this->isShowingOriginals()) {
            $requester = $linked->requesterName();

            return filled($requester) ? __($key.'original_heading', ['name' => $requester]) : __($key.'original_heading_unnamed');
        }

        return TicketPlugin::teamText($key.'escalation_heading', static::teamOf($linked));
    }

    protected function pinnedLinkedHeading(): string
    {
        $linked = $this->linkedTickets()->first();

        return $linked === null ? '' : static::linkedTicketHeading($linked);
    }

    protected function describeMembership(Ticket $record): string
    {
        $escalation = $this->escalationOf($record);
        $team = static::teamOf($escalation ?? $record);

        if ($escalation === null || $escalation->isClosed) {
            return TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.membership_closed', $team);
        }

        $others = resolve(TicketEscalationLinks::class)->linkedOriginalsQuery($escalation->getKey())
            ->whereKeyNot($record->getKey())
            ->count();

        $membership = trans_choice(
            'padmission-tickets::tickets.resources.tickets.membership'.($team === null ? '' : '_to'),
            $others,
            ['count' => $others, 'team' => $team],
        );

        if ($escalation->submitter === null) {
            return $membership;
        }

        $handledBy = $escalation->isSubmittedBy(Filament::auth()->id())
            ? TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.handled_by_you', $team)
            : TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.handled_by_other', $team, [
                'name' => resolve(GetUserDisplayName::class)->forUser($escalation->submitter),
            ]);

        return "{$membership} {$handledBy}";
    }

    /*
     * The team a ticket in another panel belongs to, as its own panel names it.
     */
    protected static function teamOf(Ticket $ticket): ?string
    {
        return $ticket->isInCurrentPanel()
            ? TicketPlugin::get()->getEscalationTargetName()
            : TicketPlugin::find($ticket->panel)?->getSupportTeamName();
    }

    protected function organizationOf(Ticket $escalation): ?string
    {
        return TicketPlugin::get()->describeTicketOrigin($escalation)
            ?? $this->linkedTickets()->map(fn (Ticket $original): ?string => TicketPlugin::get()->describeTicketOrigin($original))->filter()->first();
    }

    protected function contactHint(Ticket $escalation): string
    {
        $organization = $this->organizationOf($escalation);

        return filled($organization)
            ? __('padmission-tickets::tickets.resources.tickets.hints.contact_at', ['organization' => $organization])
            : __('padmission-tickets::tickets.resources.tickets.hints.contact');
    }

    /*
     * The escalation seen by the team that escalated it, which talks with the
     * other team here.
     */
    protected function isEscalatedElsewhere(Ticket $record): bool
    {
        return $record->isNotInCurrentPanel() && $record->isEscalation();
    }

    protected function isEscalatedHere(Ticket $record): bool
    {
        return $record->isInCurrentPanel() && $this->hasOriginals();
    }

    protected static function refuseLink(string $body): void
    {
        Notification::make()
            ->danger()
            ->title(__('padmission-tickets::tickets.resources.tickets.link_refused.title'))
            ->body($body)
            ->send();
    }

    protected function describeEscalation(Ticket $record): ?string
    {
        $key = 'padmission-tickets::tickets.resources.tickets.linked_tickets_description';

        if ($record->isNotInCurrentPanel()) {
            if (! $record->isEscalation()) {
                return null;
            }

            $team = static::teamOf($record);

            if (! $record->isClosed) {
                return TicketPlugin::teamText("{$key}.escalated_to_you", $team);
            }

            return blank($record->closed_by)
                ? TicketPlugin::teamText("{$key}.escalation_closed_unknown", $team)
                : TicketPlugin::teamText("{$key}.escalation_closed", $team, [
                    'closer' => resolve(GetUserDisplayName::class)($record->closed_by, $record->panel),
                ]);
        }

        // The organization side needs no sentence: the membership line or the two
        // escalate choices already say where the ticket stands.
        if (filled($record->linked_ticket_id) || ! $this->hasOriginals()) {
            return null;
        }

        if (TicketPlugin::get()->getLinkedConversationView() === TicketPlugin::LINKED_VIEW_MODAL) {
            return __($key.'.escalated_from');
        }

        $originals = $this->originals();
        $requester = EscalationSummary::soleRequester($originals);

        return filled($requester)
            ? __($key.'.escalated_from_beside_named', ['name' => $requester])
            : trans_choice($key.'.escalated_from_beside', $originals->count());
    }
}
