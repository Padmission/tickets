<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForPanels;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForTenants;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\TicketPlugin;

class TicketPrioritySeeder extends Seeder
{
    use SeedForPanels;
    use SeedForTenants;

    public function run(?int $tenantId = null): void
    {
        $priorityModel = TicketPlugin::resolveModelClass(TicketPriority::class);

        if ($priorityModel::query()->exists()) {
            return;
        }

        $panelBefore = Filament::getCurrentPanel();

        foreach ($this->getTenants($tenantId) as $tenantId) {
            foreach ($this->getPanels() as $panel) {
                Filament::setCurrentPanel($panel);

                $data = [
                    ['display_name' => 'Low', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 1],
                    ['display_name' => 'Medium', 'panel' => $panel->getId(), 'color' => 'Orange', 'order' => 2],
                    ['display_name' => 'High', 'panel' => $panel->getId(), 'color' => 'Red', 'order' => 3],
                ];

                foreach ($data as $row) {
                    $row = $this->addTenantColumn($row, $tenantId);
                    $priorityModel::create($row);
                }
            }
        }

        Filament::setCurrentPanel($panelBefore);
    }
}
