<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForPanels;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForTenants;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketStatusSeeder extends Seeder
{
    use SeedForPanels;
    use SeedForTenants;

    public function run(?int $tenantId = null): void
    {
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);

        if ($statusModel::query()->exists()) {
            return;
        }

        foreach ($this->getTenants($tenantId) as $tenantId) {
            foreach ($this->getPanels() as $panel) {
                Filament::setCurrentPanel($panel);

                $data = collect([
                    ['display_name' => 'Open', 'panel' => $panel->getId(), 'color' => 'Gray', 'order' => 1],
                    ['display_name' => 'In Progress', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 2],
                    ['display_name' => 'Closed', 'panel' => $panel->getId(), 'color' => 'Green', 'order' => 3],
                ]);

                foreach ($data as $row) {
                    $row = $this->addTenantColumn($row, $tenantId);
                    $statusModel::create($row);
                }
            }
        }
    }
}
