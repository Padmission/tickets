<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\TicketDisposition;

class TicketDispositionSeeder extends Seeder
{
    public function run(): void
    {
        if (TicketDisposition::exists()) {
            return;
        }

        foreach (Filament::getPanels() as $panel) {
            Filament::setCurrentPanel($panel);

            TicketDisposition::insert([
                ['display_name' => __('padmission-tickets::dispositions.resolved'), 'panel' => $panel->getId()],
                ['display_name' => __('padmission-tickets::dispositions.abandoned'), 'panel' => $panel->getId()],
                ['display_name' => __('padmission-tickets::dispositions.unresolvable'), 'panel' => $panel->getId()],
                ['display_name' => __('padmission-tickets::dispositions.withdrawn'), 'panel' => $panel->getId()],
                ['display_name' => __('padmission-tickets::dispositions.testing_training'), 'panel' => $panel->getId()],
            ]);
        }
    }
}
