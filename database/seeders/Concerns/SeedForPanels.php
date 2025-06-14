<?php

namespace Padmission\Tickets\Database\Seeders\Concerns;

use Filament\Facades\Filament;
use Filament\Panel;
use Padmission\Tickets\TicketPlugin;

trait SeedForPanels
{
    protected function getPanels(): array
    {
        return collect(Filament::getPanels())
            ->filter(fn (Panel $panel) => $panel->hasPlugin(TicketPlugin::$id))
            ->toArray();
    }
}
