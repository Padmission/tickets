<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\TicketPlugin;

class TicketPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorityModel = TicketPlugin::resolveModelClass(TicketPriority::class);

        if ($priorityModel::query()->exists()) {
            return;
        }

        foreach (Filament::getPanels() as $panel) {
            Filament::setCurrentPanel($panel);

            $data = [
                ['display_name' => 'Low', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 1],
                ['display_name' => 'Medium', 'panel' => $panel->getId(), 'color' => 'Orange', 'order' => 2],
                ['display_name' => 'High', 'panel' => $panel->getId(), 'color' => 'Red', 'order' => 3],
            ];

            foreach ($data as $row) {
                $priorityModel::create($row);
            }
        }
    }
}
