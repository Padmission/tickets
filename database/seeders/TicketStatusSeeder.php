<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketStatusSeeder extends Seeder
{
    public function run(?int $tenantId = null): void
    {
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);

        if ($statusModel::query()->exists()) {
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
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);

        foreach (Filament::getPanels() as $panel) {
            // Skip panels where TicketPlugin is not registered
            if (!TicketPlugin::isRegisteredOnPanel($panel)) {
                continue;
            }

            Filament::setCurrentPanel($panel);

            $data = collect([
                ['display_name' => 'Open', 'panel' => $panel->getId(), 'color' => 'Gray', 'order' => 1],
                ['display_name' => 'In Progress', 'panel' => $panel->getId(), 'color' => 'Blue', 'order' => 2],
                ['display_name' => 'Closed', 'panel' => $panel->getId(), 'color' => 'Green', 'order' => 3],
            ]);

            // Add tenant field if tenancy is enabled
            if ($tenantId && $tenantKey) {
                $data = $data->map(function ($row) use ($tenantId, $tenantKey) {
                    $row[$tenantKey] = $tenantId;
                    return $row;
                });
            }

            $data->each(function ($row) use ($statusModel) {
                $statusModel::create($row);
            });
        }
    }
}
