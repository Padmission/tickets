<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\TicketPlugin;

class TicketDispositionSeeder extends Seeder
{
    public function run(?int $tenantId = null): void
    {
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);

        if ($dispositionModel::query()->exists()) {
            return;
        }

        // Get tenancy configuration
        $tenancyEnabled = config('padmission-tickets.tenancy.enabled', false);
        
        if (!$tenancyEnabled) {
            // No tenancy - seed normally
            $this->seedForTenant(null);
            return;
        }

        // Get tenant model and determine foreign key
        $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
        $tenantModel = new $tenantModelClass;
        $tenantKey = Str::snake(class_basename($tenantModelClass)) . '_id';
        
        if ($tenantId !== null) {
            // Seed for specific tenant
            $this->seedForTenant($tenantId, $tenantKey);
        } else {
            // Seed for all tenants
            $tenants = $tenantModel::all();
            foreach ($tenants as $tenant) {
                $this->seedForTenant($tenant->getKey(), $tenantKey);
            }
        }
    }

    protected function seedForTenant(?int $tenantId, ?string $tenantKey = null): void
    {
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);

        foreach (Filament::getPanels() as $panel) {
            // Skip panels where TicketPlugin is not registered
            if (!TicketPlugin::isRegisteredOnPanel($panel)) {
                continue;
            }

            Filament::setCurrentPanel($panel);

            $data = collect([
                ['display_name' => 'Resolved', 'panel' => $panel->getId(), 'color' => 'Green', 'order' => 1],
                ['display_name' => 'Abandoned', 'panel' => $panel->getId(), 'color' => 'Gray', 'order' => 2],
                ['display_name' => 'Unresolvable', 'panel' => $panel->getId(), 'color' => 'Red', 'order' => 3],
                ['display_name' => 'Withdrawn', 'panel' => $panel->getId(), 'color' => 'Yellow', 'order' => 4],
                ['display_name' => 'Testing/Training', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 5],
            ]);

            // Add tenant field if tenancy is enabled
            if ($tenantId && $tenantKey) {
                $data = $data->map(function ($row) use ($tenantId, $tenantKey) {
                    $row[$tenantKey] = $tenantId;
                    return $row;
                });
            }

            $data->each(function ($row) use ($dispositionModel) {
                $dispositionModel::create($row);
            });
        }
    }
}
