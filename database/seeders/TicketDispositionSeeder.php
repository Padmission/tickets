<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForPanels;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForTenants;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\TicketPlugin;

class TicketDispositionSeeder extends Seeder
{
    use SeedForPanels;
    use SeedForTenants;

    public function run(int|string|null $tenantId = null): void
    {
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);

        if ($dispositionModel::query()->exists()) {
            return;
        }

        foreach ($this->getTenants($tenantId) as $tenantId) {
            foreach ($this->getPanels() as $panel) {
                Filament::setCurrentPanel($panel);

                $data = [
                    ['display_name' => 'Resolved', 'panel' => $panel->getId(), 'color' => 'Green', 'order' => 1],
                    ['display_name' => 'Abandoned', 'panel' => $panel->getId(), 'color' => 'Gray', 'order' => 2],
                    ['display_name' => 'Unresolvable', 'panel' => $panel->getId(), 'color' => 'Red', 'order' => 3],
                    ['display_name' => 'Withdrawn', 'panel' => $panel->getId(), 'color' => 'Yellow', 'order' => 4],
                    ['display_name' => 'Testing/Training', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 5],
                ];

                foreach ($data as $row) {
                    $row = $this->addTenantColumn($row, $tenantId);
                    $dispositionModel::create($row);
                }
            }
        }
    }
}
