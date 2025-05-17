<?php

namespace Padmission\Tickets;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Padmission\Tickets\Filament\Resources;

final class TicketPlugin implements Plugin
{
    public static string $id = 'padmission-tickets';

    protected string $escalationLevel = 'default';

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
        $panel
            ->resources([
                Resources\Tickets\TicketResource::class,
                Resources\Statuses\StatusResource::class,
                Resources\Priorities\PriorityResource::class,
            ]);
    }

    public function boot(Panel $panel): void {}

    public static function get(): static
    {
        $plugin = Filament::getPlugin(static::$id);
        assert($plugin instanceof static);

        return $plugin;
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
}
