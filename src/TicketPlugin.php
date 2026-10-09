<?php

namespace Padmission\Tickets;

use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\Column;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Filament\Resources\Dispositions\DispositionResource;
use Padmission\Tickets\Filament\Resources\Priorities\PriorityResource;
use Padmission\Tickets\Filament\Resources\Statuses\StatusResource;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\OverdueTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketBurndownChartWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Support\ConversationViewer;
use RuntimeException;

class TicketPlugin implements Plugin
{
    public static string $id = 'padmission-tickets';

    public const FIELD_HELP_INLINE = 'inline';

    public const FIELD_HELP_SUMMARY = 'summary';

    public const FIELD_HELP_TOOLTIP = 'tooltip';

    public const KEEP_WAITING_CHECKBOX = 'checkbox';

    /*
     * How the chat and everything beside it show a message's time.
     */
    public const MESSAGE_TIME_FORMAT = 'M j, g:i A';

    public const LINKED_VIEW_MODAL = 'modal';

    public const LINKED_VIEW_BESIDE = 'beside';

    public const LINKED_VIEW_DRAWER = 'drawer';

    public const KEEP_WAITING_BUTTON = 'button';

    protected ?Panel $panel = null;

    protected bool $shouldRegisterResources = false;

    /** @var class-string<ListTickets> */
    protected string $listPage = ListTickets::class;

    protected bool $shouldRegisterWidgets = false;

    protected bool $shouldRegisterConfigurationResources = true;

    protected bool $shouldEnableLinkedTickets = false;

    public ?array $linkTicketsToPanels = null;

    protected string $escalationLevel = 'default';

    protected mixed $supportTeamName = null;

    protected ?Closure $userDescriber = null;

    protected mixed $assignableUsersDescription = null;

    protected ?Closure $ticketOriginDescriber = null;

    protected string $fieldHelp = self::FIELD_HELP_TOOLTIP;

    protected string $keepWaitingStyle = self::KEEP_WAITING_BUTTON;

    protected string $linkedConversationView = self::LINKED_VIEW_BESIDE;

    protected bool $pinLinkedConversation = false;

    protected bool $navigationBadgeCountsNeedsYou = false;

    public const int DEFAULT_REOPEN_WINDOW_DAYS = 30;

    protected ?Closure $personActions = null;

    protected ?Closure $replyDisabledReason = null;

    protected int|Closure $reopenWindowDays = self::DEFAULT_REOPEN_WINDOW_DAYS;

    protected mixed $additionalTicketDetails = [];

    protected mixed $additionalTableColumns = [];

    protected ?AssignmentStrategy $assignmentStrategy = null;

    protected mixed $shouldShowChatWidget = false;

    protected mixed $chatWidgetConfig = null;

    protected ?NotificationConfiguration $notificationConfiguration = null;

    protected ?string $targetPanelId = null;

    protected mixed $allSupportersQuery = null;

    protected ?string $supporterMatchColumn = null;

    protected mixed $currentUserAssigneeIds = null;

    protected mixed $initialAssignmentSupportersQuery = null;

    protected mixed $customTicketQuery = null;

    protected mixed $relationshipScopeModifier = null;

    protected ?Closure $requestersQuery = null;

    protected bool|Closure|null $startsTickets = null;

    protected ?Closure $ticketTenantsQuery = null;

    protected string $dateTimeDisplayFormat = 'd.m.Y H:i:s';

    protected string|Closure|null $displayTimezone = null;

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return static::$id;
    }

    public function register(Panel $panel): void
    {
        $this->panel = $panel;

        if ($this->shouldRegisterResources()) {
            $panel->livewireComponents([$this->getListPage()]);

            $panel->resources([
                TicketResource::class,
                ...($this->shouldRegisterConfigurationResources() ? [
                    StatusResource::class,
                    DispositionResource::class,
                    PriorityResource::class,
                ] : []),
            ]);
        }

        if ($this->shouldRegisterWidgets()) {
            $panel->widgets([
                OpenTicketsWidget::class,
                OpenSupporterTickets::class,
                TicketCloseTimeWidget::class,
                OverdueTicketsWidget::class,
                TicketBurndownChartWidget::class,
            ]);
        }

        // Always register the render hook, but check the condition inside the closure
        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            function () use ($panel) {
                // Check the condition when the hook is actually rendered (after auth)
                if (! $this->shouldShowChatWidget()) {
                    return '';
                }

                return view('padmission-tickets::filament.chat-widget', [
                    'primaryColor' => data_get($panel->getColors(), 'primary', null),
                ]);
            }
        );
    }

    public function boot(Panel $panel): void
    {
        if ($this->shouldRegisterResources && ! $this->allSupportersQuery) {
            throw new RuntimeException(
                "The TicketPlugin on panel '{$panel->getId()}' requires an allSupportersQuery() ".
                'to be configured when registering resources. This defines all users who can support tickets in this panel.'
            );
        }
    }

    public static function get(?string $panelId = null): static
    {
        $panel = $panelId ? Filament::getPanel($panelId) : Filament::getCurrentOrDefaultPanel();

        /**
         * @var static $plugin
         */
        $plugin = $panel->getPlugin(static::$id);

        return $plugin;
    }

    /*
     * A host may leave a panel's plugin unregistered in some processes, such
     * as queue workers, so a ticket's panel can lack one.
     */
    public static function find(?string $panelId): ?static
    {
        $panel = Filament::getPanels()[$panelId] ?? null;

        if (! $panel?->hasPlugin(static::$id)) {
            return null;
        }

        /** @var static */
        return $panel->getPlugin(static::$id);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return class-string<T>
     */
    public static function resolveModelClass(string $class): string
    {
        $classes = config()->array('padmission-tickets.models');

        return (string) ($classes[$class] ?? $class);
    }

    /**
     * @return class-string<Authenticatable&Model>
     */
    public static function resolveUserModelClass(): string
    {
        $classes = config()->array('padmission-tickets.models');

        return (string) ($classes[Authenticatable::class]);
    }

    /**
     * @param  class-string  $class
     * @return class-string
     */
    public static function resolveJobClass(string $class): string
    {
        $jobs = config()->array('padmission-tickets.jobs');

        return (string) ($jobs[$class] ?? $class);
    }

    /* Configuration options */
    /**
     * @param  class-string<ListTickets>  $page
     */
    public function listPage(string $page): static
    {
        if (! is_a($page, ListTickets::class, true)) {
            throw new InvalidArgumentException('The ticket list page must extend '.ListTickets::class.'.');
        }

        $this->listPage = $page;

        return $this;
    }

    /**
     * @return class-string<ListTickets>
     */
    public function getListPage(): string
    {
        return $this->listPage;
    }

    public function dateTimeDisplayFormat(string $format): self
    {
        $this->dateTimeDisplayFormat = $format;

        return $this;
    }

    public function getDateTimeDisplayFormat(): string
    {
        return $this->dateTimeDisplayFormat;
    }

    /*
     * The timezone every ticket time is shown in: the chat, the linked pane
     * and the tooltips. Without it the chat followed the browser while the
     * rest of the page followed the server.
     */
    public function displayTimezone(string|Closure|null $timezone): static
    {
        $this->displayTimezone = $timezone;

        return $this;
    }

    public function getDisplayTimezone(): string
    {
        $timezone = $this->displayTimezone instanceof Closure ? app()->call($this->displayTimezone) : $this->displayTimezone;

        return filled($timezone) ? (string) $timezone : FilamentTimezone::get();
    }

    /*
     * A ticket time as the page shows it, in the display timezone.
     */
    public static function formatMessageTime(?CarbonInterface $time): ?string
    {
        return $time?->copy()->setTimezone(static::get()->getDisplayTimezone())->format(static::MESSAGE_TIME_FORMAT);
    }

    public function escalationLevel(string $level): static
    {
        $this->escalationLevel = $level;

        return $this;
    }

    public function getEscalationLevel(): string
    {
        return $this->escalationLevel;
    }

    public function assignmentStrategy(AssignmentStrategy $strategy): static
    {
        $this->assignmentStrategy = $strategy;

        return $this;
    }

    public function getAssignmentStrategy(): ?AssignmentStrategy
    {
        return $this->assignmentStrategy;
    }

    public function registerResources(bool $shouldRegister = true, bool $shouldRegisterWidgets = false): static
    {
        $this->shouldRegisterResources = $shouldRegister;
        $this->shouldRegisterWidgets = $shouldRegisterWidgets;

        return $this;
    }

    public function shouldRegisterResources(): bool
    {
        return $this->shouldRegisterResources;
    }

    /*
     * Each organization configures its own statuses, priorities and dispositions
     * on its own panel. A panel that only answers other panels' tickets has no
     * organization of its own to configure, and a host clusters these elsewhere,
     * so it turns them off rather than route to a cluster it never registered.
     */
    public function registerConfigurationResources(bool $shouldRegister = true): static
    {
        $this->shouldRegisterConfigurationResources = $shouldRegister;

        return $this;
    }

    public function shouldRegisterConfigurationResources(): bool
    {
        return $this->shouldRegisterConfigurationResources;
    }

    public function shouldRegisterWidgets(): bool
    {
        return $this->shouldRegisterWidgets;
    }

    /**
     * @param  array<string>|null  $panelIds
     */
    public function allowLinkedTicketsTo(?array $panelIds = null): static
    {
        $this->linkTicketsToPanels = $panelIds;

        return $this;
    }

    public function hasLinkedTickets(): bool
    {
        return count($this->getLinkedTicketChildPanels()) > 0
            || count($this->getLinkedTicketParentPanels()) > 0;
    }

    /**
     * Get panels that can link to the current panel.
     */
    public function getLinkedTicketChildPanels(): array
    {
        // return once(function (): array {
        $panels = [];
        $currentPanel = $this->panel;

        foreach (Filament::getPanels() as $panel) {
            if (! $panel->hasPlugin(TicketPlugin::$id)) {
                continue;
            }

            /**
             * @var TicketPlugin $plugin
             */
            $plugin = $panel->getPlugin(TicketPlugin::$id);

            if (array_key_exists($currentPanel->getId(), $plugin->getLinkedTicketParentPanels())) {
                $panels[$panel->getId()] = $panel;
            }
        }

        return $panels;
        // });
    }

    /**
     * Get panels the current panel can create linked tickets in.
     */
    public function getLinkedTicketParentPanels(): array
    {
        // return once(function (): array {
        $panels = Filament::getPanels();

        if ($this->linkTicketsToPanels === null) {
            return [];
        }

        $filteredPanels = array_filter(
            $panels,
            fn (Panel $panel) => in_array($panel->getId(), $this->linkTicketsToPanels),
        );

        return $filteredPanels;
        // });
    }

    public function supportTeamName(string|Closure|null $name): static
    {
        $this->supportTeamName = $name;

        return $this;
    }

    public function getSupportTeamName(): ?string
    {
        if ($this->supportTeamName instanceof Closure) {
            return app()->call($this->supportTeamName);
        }

        return $this->supportTeamName;
    }

    public function getEscalationTargetName(): ?string
    {
        $panels = $this->getLinkedTicketParentPanels();

        if (count($panels) !== 1) {
            return null;
        }

        return static::getSupportTeamNameForPanel(reset($panels));
    }

    /*
     * Each string that names a team has a `_to` variant with a :team
     * placeholder, used when the team has a name, so neither version needs
     * a vague stand-in such as "the other team".
     */
    public static function teamText(string $key, ?string $team, array $replace = []): string
    {
        return $team === null
            ? __($key, $replace)
            : __("{$key}_to", [...$replace, 'team' => $team]);
    }

    public static function getSupportTeamNameForPanel(Panel $panel): ?string
    {
        if (! $panel->hasPlugin(static::$id)) {
            return null;
        }

        /** @var static $plugin */
        $plugin = $panel->getPlugin(static::$id);

        return $plugin->getSupportTeamName();
    }

    /**
     * @param  self::LINKED_VIEW_*  $view
     */
    public function linkedConversationView(string $view): static
    {
        $this->linkedConversationView = $view;

        return $this;
    }

    public function getLinkedConversationView(): string
    {
        return $this->linkedConversationView;
    }

    public function pinLinkedConversation(bool $condition = true): static
    {
        $this->pinLinkedConversation = $condition;

        return $this;
    }

    public function shouldPinLinkedConversation(): bool
    {
        return $this->pinLinkedConversation;
    }

    /*
     * The sidebar badge counts what the Needs You card counts rather than the
     * viewer's open assigned tickets. Turned on per panel: an organization's
     * own panel wants the tickets needing them, a panel like Padmission's own
     * the tickets it holds.
     */
    public function navigationBadgeCountsNeedsYou(bool $condition = true): static
    {
        $this->navigationBadgeCountsNeedsYou = $condition;

        return $this;
    }

    public function shouldNavigationBadgeCountNeedsYou(): bool
    {
        return $this->navigationBadgeCountsNeedsYou;
    }

    /*
     * How long after a ticket closes its requester may still reopen it by
     * replying. After that a reply starts a new ticket that links back.
     */
    public function reopenWindowDays(int|Closure $days): static
    {
        $this->reopenWindowDays = $days;

        return $this;
    }

    public function getReopenWindowDays(): int
    {
        return (int) value($this->reopenWindowDays);
    }

    /*
     * Actions the host offers beside each person a ticket names, such as
     * impersonating them. Their rules stay the host's.
     */
    public function personActionsUsing(?Closure $callback): static
    {
        $this->personActions = $callback;

        return $this;
    }

    /**
     * @return list<Action>
     */
    public function getPersonActions(Model $person, Ticket $ticket): array
    {
        if ($this->personActions === null) {
            return [];
        }

        return array_values(array_filter(
            (array) app()->call($this->personActions, ['person' => $person, 'ticket' => $ticket]),
            fn (mixed $action): bool => $action instanceof Action,
        ));
    }

    /*
     * Lets a host stop someone writing in the chat for a reason of its own,
     * such as a session that may only look. The callback is given the ticket
     * and the user, and returns null when they may reply or the reason they
     * may not, which the chat shows in its reply box and the server sends
     * back when it refuses a message.
     */
    public function replyDisabledUsing(?Closure $callback): static
    {
        $this->replyDisabledReason = $callback;

        return $this;
    }

    public function getReplyDisabledReason(Ticket $ticket, Authenticatable $user): ?string
    {
        if ($this->replyDisabledReason === null) {
            return null;
        }

        $reason = app()->call($this->replyDisabledReason, ['ticket' => $ticket, 'user' => $user]);

        return filled($reason) ? (string) $reason : null;
    }

    /**
     * @param  self::KEEP_WAITING_*  $style
     */
    public function keepWaitingStyle(string $style): static
    {
        $this->keepWaitingStyle = $style;

        return $this;
    }

    public function getKeepWaitingStyle(): string
    {
        return $this->keepWaitingStyle;
    }

    /**
     * @param  self::FIELD_HELP_*  $style
     */
    public function fieldHelp(string $style): static
    {
        $this->fieldHelp = $style;

        return $this;
    }

    public function getFieldHelp(): string
    {
        return $this->fieldHelp;
    }

    /**
     * @param  (Closure(Ticket $ticket): ?string)|null  $callback
     */
    public function describeTicketOriginUsing(?Closure $callback): static
    {
        $this->ticketOriginDescriber = $callback;

        return $this;
    }

    public function describeTicketOrigin(Ticket $ticket): ?string
    {
        return $this->ticketOriginDescriber === null ? null : ($this->ticketOriginDescriber)($ticket);
    }

    public function assignableUsersDescription(string|Closure|null $description): static
    {
        $this->assignableUsersDescription = $description;

        return $this;
    }

    public function getAssignableUsersDescription(): ?string
    {
        if ($this->assignableUsersDescription instanceof Closure) {
            return app()->call($this->assignableUsersDescription);
        }

        return $this->assignableUsersDescription;
    }

    /**
     * The callback may return one description or a list, such as role names.
     *
     * @param  (Closure(Model $user, Ticket $ticket): (string|list<string>|null))|null  $callback
     */
    public function describeUsersUsing(?Closure $callback): static
    {
        $this->userDescriber = $callback;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function describeUser(?Model $user, Ticket $ticket): array
    {
        if ($user === null || $this->userDescriber === null) {
            return [];
        }

        return array_values(array_filter((array) ($this->userDescriber)($user, $ticket), 'filled'));
    }

    /**
     * @param  array<Component>|Closure(): array<Component>  $components
     */
    public function additionalTicketDetails(array|Closure $components): static
    {
        $this->additionalTicketDetails = $components;

        return $this;
    }

    /**
     * @return array<Component>
     */
    public function getAdditionalTicketDetails(): array
    {
        if ($this->additionalTicketDetails instanceof Closure) {
            return app()->call($this->additionalTicketDetails);
        }

        return $this->additionalTicketDetails;
    }

    /**
     * @param  array<Column>|Closure(): array<Column>  $columns
     */
    public function additionalTableColumns(array|Closure $columns): static
    {
        $this->additionalTableColumns = $columns;

        return $this;
    }

    /**
     * @return array<Column>
     */
    public function getAdditionalTableColumns(): array
    {
        if ($this->additionalTableColumns instanceof Closure) {
            return app()->call($this->additionalTableColumns);
        }

        return $this->additionalTableColumns;
    }

    public function showChatWidget(bool|Closure $shouldShow = true, ChatWidgetConfig|Closure|null $config = null): static
    {
        $this->shouldShowChatWidget = $shouldShow;
        $this->chatWidgetConfig = $config;

        return $this;
    }

    public function getChatWidgetConfig(): ChatWidgetConfig
    {
        if ($this->chatWidgetConfig instanceof Closure) {
            return app()->call($this->chatWidgetConfig);
        }

        return $this->chatWidgetConfig ?? new ChatWidgetConfig;
    }

    public function notificationConfiguration(NotificationConfiguration $configuration): static
    {
        $this->notificationConfiguration = $configuration;

        return $this;
    }

    public function getNotificationConfiguration(): NotificationConfiguration
    {
        return $this->notificationConfiguration ?? NotificationConfiguration::make();
    }

    public function targetPanel(string $panelId): static
    {
        $this->targetPanelId = $panelId;

        return $this;
    }

    public function getTargetPanelId(): ?string
    {
        return $this->targetPanelId;
    }

    public function shouldShowChatWidget(): bool
    {
        if ($this->shouldShowChatWidget instanceof Closure) {
            return (bool) app()->call($this->shouldShowChatWidget);
        }

        return $this->shouldShowChatWidget;
    }

    /**
     * The closure may declare an optional `Padmission\Tickets\Models\Ticket $ticket`
     * parameter; it is provided when supporters are resolved for a specific ticket
     * (e.g. notification fallback when the ticket has no assignee). In multi-tenant
     * hosts the closure MUST scope the query by the ticket's tenant whenever the
     * ticket is provided, because resolution can run in queued or console contexts
     * where the host's tenant scope is not bound.
     */
    public function allSupportersQuery(Closure|Builder $query): static
    {
        $this->allSupportersQuery = $query;

        return $this;
    }

    public function getAllSupportersQuery(): ?Closure
    {
        if ($this->allSupportersQuery instanceof Builder) {
            return fn () => clone $this->allSupportersQuery;
        }

        return $this->allSupportersQuery;
    }

    /*
     * A pool that keeps one account per person (by email, say) leaves out
     * that person's other accounts, so an assignee is matched on this column.
     */
    public function matchSupportersBy(?string $column): static
    {
        $this->supporterMatchColumn = $column;

        return $this;
    }

    public function getSupporterMatchColumn(): string
    {
        return $this->supporterMatchColumn ?? 'id';
    }

    /**
     * @param  Closure(): array<int, int|string>  $callback
     */
    public function currentUserAssigneeIds(Closure $callback): static
    {
        $this->currentUserAssigneeIds = $callback;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getCurrentUserAssigneeIds(): array
    {
        if ($this->currentUserAssigneeIds instanceof Closure) {
            $ids = app()->call($this->currentUserAssigneeIds);

            return array_values(array_unique(array_map('intval', $ids)));
        }

        $id = Filament::auth()->id() ?? auth()->id();

        return $id !== null ? [(int) $id] : [];
    }

    public function initialAssignmentSupportersQuery(Closure|Builder $query): static
    {
        $this->initialAssignmentSupportersQuery = $query;

        return $this;
    }

    public function getInitialAssignmentSupportersQuery(): ?Closure
    {
        if ($this->initialAssignmentSupportersQuery instanceof Builder) {
            return fn () => clone $this->initialAssignmentSupportersQuery;
        }

        return $this->initialAssignmentSupportersQuery;
    }

    public function customizeTicketQuery(Closure $query): static
    {
        $this->customTicketQuery = $query;

        return $this;
    }

    /**
     * @return Builder<Ticket>
     */
    public function getTicketQuery(): Builder
    {
        $baseQuery = static::resolveModelClass(Ticket::class)::query();

        if ($this->customTicketQuery) {
            return app()->call($this->customTicketQuery, ['query' => $baseQuery]);
        }

        return $baseQuery;
    }

    /*
     * The people a supporter may open a ticket for from the ticket list,
     * before the search narrows them. Left unset, it is every user the host's
     * own scopes let this panel see, which for a tenant panel should be the
     * tenant's users.
     */
    public function requestersQuery(?Closure $query): static
    {
        $this->requestersQuery = $query;

        return $this;
    }

    /**
     * @return Builder<Model>
     */
    public function getRequestersQuery(): Builder
    {
        if ($this->requestersQuery !== null) {
            return app()->call($this->requestersQuery);
        }

        return static::resolveUserModelClass()::query();
    }

    /*
     * Whether supporters may start a ticket from the ticket list. By default
     * a panel that receives other teams' escalations does not, since its
     * people answer other organizations rather than their own.
     */
    public function startsTickets(bool|Closure|null $condition = true): static
    {
        $this->startsTickets = $condition;

        return $this;
    }

    public function canStartTickets(): bool
    {
        if ($this->startsTickets === null) {
            return count($this->getLinkedTicketChildPanels()) === 0;
        }

        return (bool) value($this->startsTickets);
    }

    /*
     * The organizations a panel that receives other teams' escalations may
     * open a ticket for, when tickets belong to a tenant. That panel sees
     * every tenant, so the host says which of them take tickets.
     */
    public function ticketTenantsQuery(?Closure $query): static
    {
        $this->ticketTenantsQuery = $query;

        return $this;
    }

    /**
     * @return Builder<Model>|null
     */
    public function getTicketTenantsQuery(): ?Builder
    {
        return $this->ticketTenantsQuery === null ? null : app()->call($this->ticketTenantsQuery);
    }

    /*
     * Whether the user is in this panel's supporter pool, read from the page's
     * viewer when it is them rather than by another query.
     */
    public function isSupporter(Model $user): bool
    {
        $viewer = ConversationViewer::current();

        if ($viewer->panelId === $this->panel?->getId() && (string) $viewer->userId === (string) $user->getKey()) {
            return $viewer->isSupporter;
        }

        $supportersQuery = $this->getAllSupportersQuery();

        if ($supportersQuery === null) {
            return false;
        }

        return app()->call($supportersQuery)
            ->where($this->getSupporterMatchColumn(), $user->getAttribute($this->getSupporterMatchColumn()))
            ->exists();
    }

    /**
     * Whether a pool holds the user, matched on matchSupportersBy()'s column as
     * the page's viewer matches it: emails whatever their case.
     *
     * @param  Builder<Model>  $pool
     */
    public function poolHas(Builder $pool, Model $user): bool
    {
        $column = $this->getSupporterMatchColumn();

        if ($column === $user->getKeyName()) {
            return $pool->whereKey($user->getKey())->exists();
        }

        $value = $user->getAttribute($column);

        if (blank($value)) {
            return false;
        }

        return $pool->whereRaw('LOWER('.$pool->getQuery()->getGrammar()->wrap($pool->qualifyColumn($column)).') = ?', [mb_strtolower((string) $value)])->exists();
    }

    public function modifyRelationshipScopes(Closure $callback): static
    {
        $this->relationshipScopeModifier = $callback;

        return $this;
    }

    public function getRelationshipScopeModifier(): ?Closure
    {
        return $this->relationshipScopeModifier;
    }
}
