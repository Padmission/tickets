<?php

namespace Padmission\Tickets;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\Filament\Resources;
use Padmission\Tickets\NotificationStrategies\NotificationStrategy;

final class TicketPlugin implements Plugin
{
    public static string $id = 'padmission-tickets';

    protected string $escalationLevel = 'default';

    protected ?AssignmentStrategy $assignmentStrategy = null;

    protected ?NotificationStrategy $notificationStrategy = null;

    public static function make(): static
    {
        return new static;
    }

    public function getId(): string
    {
        return static::$id;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\Tickets\TicketResource::class,
                Resources\Statuses\StatusResource::class,
                Resources\Priorities\PriorityResource::class,
            ]);
    }

    public function boot(Panel $panel): void {}

    public static function get(?string $panelId = null): static
    {
        $panel = $panelId ? Filament::getPanel($panelId) : Filament::getCurrentPanel();
        $plugin = $panel->getPlugin(static::$id);

        assert($plugin instanceof static);

        return $plugin;
    }

    /**
     * @param  class-string  $class
     * @return class-string<Model>
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
}
