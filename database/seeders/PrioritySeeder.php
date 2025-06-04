<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\Priority;

class PrioritySeeder extends Seeder
{
    public function run(): void
    {
        if (Priority::query()->exists()) {
            return;
        }

        foreach (Filament::getPanels() as $panel) {
            Filament::setCurrentPanel($panel);

            Priority::insert([
                ['display_name' => 'Low', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 1],
                ['display_name' => 'Medium', 'panel' => $panel->getId(), 'color' => 'Orange', 'order' => 2],
                ['display_name' => 'High', 'panel' => $panel->getId(), 'color' => 'Red', 'order' => 3],
            ]);
        }
    }
}
