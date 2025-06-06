<?php

namespace Padmission\Tickets;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\Filament\Resources;
use Padmission\Tickets\NotificationStrategies\NotificationStrategy;

final class TicketPlugin implements Plugin
{
    public static string $id = 'padmission-tickets';

    protected bool $shouldRegisterResources = false;

    protected string $escalationLevel = 'default';

    protected ?AssignmentStrategy $assignmentStrategy = null;

    protected ?NotificationStrategy $notificationStrategy = null;

    protected bool $shouldShowChatWidget = false;

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
                Resources\Priorities\PriorityResource::class,
            ]);
        }

        if ($this->shouldShowChatWidget()) {
            $panel->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('padmission-tickets::filament.chat-widget', [
                    'primaryColor' => data_get($panel->getColors(), 'primary', null),
                ])
            );
        }
    }

    public function boot(Panel $panel): void {}

    public static function get(?string $panelId = null): static
    {
        $panel = $panelId ? Filament::getPanel($panelId) : Filament::getCurrentPanel();

        /**
         * @var static $plugin
         */
        $plugin = $panel->getPlugin(static::$id);

        return $plugin;
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T|Authenticatable>  $class
     * @return class-string<T>
     */
    public static function resolveModelClass(string $class): string
    {
        $classes = config()->array('padmission-tickets.models');

        return (string) ($classes[$class] ?? $class);
    }

    /* Configuration options */
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

    public function notificationStrategy(NotificationStrategy $strategy): static
    {
        $this->notificationStrategy = $strategy;

        return $this;
    }

    public function getNotificationStrategy(): ?NotificationStrategy
    {
        return $this->notificationStrategy;
    }

    public function registerResources(bool $shouldRegister = true): static
    {
        $this->shouldRegisterResources = $shouldRegister;

        return $this;
    }

    public function shouldRegisterResources(): bool
    {
        return $this->shouldRegisterResources;
    }

    public function showChatWidget(bool $shouldShow = true): static
    {
        $this->shouldShowChatWidget = $shouldShow;

        return $this;
    }

    public function shouldShowChatWidget(): bool
    {
        return $this->shouldShowChatWidget;
    }
}
