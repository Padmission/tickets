<?php

namespace Padmission\Tickets;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Filament\Resources;
use Padmission\Tickets\Filament\Widgets;
use RuntimeException;

final class TicketPlugin implements Plugin
{
    public static string $id = 'padmission-tickets';

    protected bool $shouldRegisterResources = false;

    protected bool $shouldRegisterWidgets = false;

    protected string $escalationLevel = 'default';

    protected ?AssignmentStrategy $assignmentStrategy = null;

    protected mixed $shouldShowChatWidget = false;

    protected mixed $chatWidgetConfig = null;

    protected ?NotificationConfiguration $notificationConfiguration = null;

    protected ?string $targetPanelId = null;

    protected mixed $allSupportersQuery = null;

    protected mixed $initialAssignmentSupportersQuery = null;

    protected string $dateTimeDisplayFormat = 'd.m.Y H:i:s';

    public static function make(): static
    {
        return new self;
    }

    public function getId(): string
    {
        return static::$id;
    }

    public function register(Panel $panel): void
    {
        if ($this->shouldRegisterResources()) {
            $panel->resources([
                Resources\Tickets\TicketResource::class,
                Resources\Statuses\StatusResource::class,
                Resources\Dispositions\DispositionResource::class,
                Resources\Priorities\PriorityResource::class,
            ]);
        }

        if ($this->shouldRegisterWidgets()) {
            $panel->widgets([
                Widgets\OpenTicketsWidget::class,
                Widgets\OpenSupporterTickets::class,
                Widgets\TicketCloseTimeWidget::class,
                Widgets\TicketBurndownChartWidget::class,
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
    public function dateTimeDisplayFormat(string $format): self
    {
        $this->dateTimeDisplayFormat = $format;

        return $this;
    }

    public function getDateTimeDisplayFormat(): string
    {
        return $this->dateTimeDisplayFormat;
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

    public function shouldRegisterWidgets(): bool
    {
        return $this->shouldRegisterWidgets;
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
}
