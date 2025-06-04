<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\Status;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        if (Status::query()->exists()) {
            return;
        }

        foreach (Filament::getPanels() as $panel) {
            Filament::setCurrentPanel($panel);

            Status::insert([
                ['display_name' => 'Open', 'panel' => $panel->getId(), 'color' => 'Gray', 'order' => 1],
                ['display_name' => 'In Progress', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 2],
                ['display_name' => 'Closed', 'panel' => $panel->getId(), 'color' => 'Green', 'order' => 3],
            ]);
        }
    }
}
