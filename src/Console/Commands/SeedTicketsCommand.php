<?php

namespace Padmission\Tickets\Console\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Padmission\Tickets\Database\Seeders\TicketDispositionSeeder;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketSeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\TicketPlugin;

class SeedTicketsCommand extends Command
{
    protected $signature = 'tickets:seed
                            {--tenant= : Specific tenant ID to seed for (optional)}
                            {--only= : Seed only specific types (comma-separated): dispositions,priorities,statuses,tickets}
                            {--force : Force seeding even if data already exists}';

    protected $description = 'Seed ticket data for all tenants or a specific tenant';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $only = $this->option('only');
        $force = $this->option('force');

        // Check if tenancy is enabled
        $tenancyEnabled = config('padmission-tickets.tenancy.enabled', false);

        // Show panel information
        $this->showPanelInfo();

        if (! $tenancyEnabled) {
            $this->info('Tenancy is not enabled. Seeding without tenant context.');
        } else {
            if ($tenantId) {
                $this->info("Seeding ticket data for tenant: {$tenantId}");
            } else {
                $this->info('Seeding ticket data for all tenants');

                // Get all tenants
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $tenants = $tenantModelClass::all();

                if ($tenants->isEmpty()) {
                    $this->warn('No tenants found. Please create tenants first.');

                    return self::FAILURE;
                }

                $this->info("Found {$tenants->count()} tenant(s) to seed for");
            }
        }

        $seedersToRun = $this->determineSeedersToRun($only);

        if (empty($seedersToRun)) {
            $this->error('No valid seeders specified.');

            return self::FAILURE;
        }

        // Run the seeders
        foreach ($seedersToRun as $seederName => $seederClass) {
            $this->info("Seeding {$seederName}...");

            try {
                if ($force) {
                    // TODO: If forced, we might need to handle existing data differently
                    $this->warn('Force flag is set - this may create duplicate data');
                }

                $seeder = new $seederClass;
                $seeder->run($tenantId ? (int) $tenantId : null);

                $this->line("✅ {$seederName} completed successfully");

            } catch (\Exception $e) {
                $this->error("❌ {$seederName} failed: ".$e->getMessage());

                if ($this->option('verbose')) {
                    $this->error($e->getTraceAsString());
                }

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('🎉 All ticket seeders completed successfully!');

        return self::SUCCESS;
    }

    protected function determineSeedersToRun(?string $only): array
    {
        $allSeeders = [
            'dispositions' => TicketDispositionSeeder::class,
            'priorities' => TicketPrioritySeeder::class,
            'statuses' => TicketStatusSeeder::class,
            'tickets' => TicketSeeder::class,
        ];

        if (! $only) {
            return $allSeeders;
        }

        $requestedTypes = array_map(trim(...), explode(',', $only));

        return collect($allSeeders)
            ->filter(fn ($value, $key) => in_array($key, $requestedTypes))
            ->toArray();
    }

    protected function showPanelInfo(): void
    {
        $allPanels = Filament::getPanels();
        $panelsWithPlugin = [];
        $panelsWithoutPlugin = [];

        foreach ($allPanels as $panel) {
            if ($panel->hasPlugin(TicketPlugin::$id)) {
                $panelsWithPlugin[] = $panel->getId();
            } else {
                $panelsWithoutPlugin[] = $panel->getId();
            }
        }

        if (! empty($panelsWithPlugin)) {
            $this->info('Panels with TicketPlugin registered: '.implode(', ', $panelsWithPlugin));
        }

        if (! empty($panelsWithoutPlugin)) {
            $this->comment('Panels without TicketPlugin (will be skipped): '.implode(', ', $panelsWithoutPlugin));
        }

        $this->newLine();
    }
}
