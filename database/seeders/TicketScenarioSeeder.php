<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForPanels;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForTenants;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Seeding\TicketScenarios;
use Padmission\Tickets\TicketPlugin;

/*
 * A panel another panel escalates to is skipped: its tickets come from the
 * panels that escalate to it. Whether it may start tickets says nothing
 * here, since a host turns that on for its escalation target so staff can
 * open tickets for contacts.
 */
class TicketScenarioSeeder extends Seeder
{
    use SeedForPanels;
    use SeedForTenants;

    public ?string $panelId = null;

    /** @var list<string> */
    public array $skipped = [];

    public function run(int|string|null $tenantId = null): void
    {
        TicketScenarios::refuseOutsideDemoEnvironments();

        // Each skips a panel and tenant that already has rows.
        (new TicketStatusSeeder)->run($tenantId);
        (new TicketPrioritySeeder)->run($tenantId);
        (new TicketDispositionSeeder)->run($tenantId);

        $panelBefore = Filament::getCurrentPanel();

        foreach ($this->getTenants($tenantId) as $tenant) {
            foreach ($this->getPanels() as $panel) {
                if (($this->panelId !== null && $panel->getId() !== $this->panelId) || $this->pluginOf($panel)->getLinkedTicketChildPanels() !== []) {
                    continue;
                }

                Filament::setCurrentPanel($panel);

                $target = array_key_first($this->pluginOf($panel)->getLinkedTicketParentPanels());
                $supporters = $this->supporters($panel->getId(), $tenant);
                $targetSupporters = $target === null ? collect() : $this->supporters($target, $tenant);
                $requesters = $this->requesters($panel, $tenant, $supporters->merge($targetSupporters));

                if ($supporters->isEmpty() || $requesters->isEmpty()) {
                    $this->skipped[] = $panel->getId().($tenant === null ? '' : " (tenant {$tenant})");

                    continue;
                }

                $scenarios = TicketScenarios::make($panel->getId(), $tenant, $requesters, $supporters);

                if ($target !== null) {
                    $scenarios->escalatesTo($target, $targetSupporters);
                }

                $scenarios->all();
            }
        }

        Filament::setCurrentPanel($panelBefore);
    }

    /**
     * @return Collection<int, Model>
     */
    protected function supporters(string $panelId, int|string|null $tenant): Collection
    {
        $query = TicketPlugin::find($panelId)?->getAllSupportersQuery();

        if ($query === null) {
            return collect();
        }

        // The query is told the ticket, so a multi-tenant host scopes it to the tenant.
        $draft = (new (TicketPlugin::resolveModelClass(Ticket::class)))->forceFill([
            'panel' => $panelId,
            ...($tenant === null ? [] : [$this->getTenantKey() => $tenant]),
        ]);

        return app()->call($query, ['ticket' => $draft])->limit(3)->get();
    }

    /**
     * @param  Collection<int, Model>  $supporters
     * @return Collection<int, Model>
     */
    protected function requesters(Panel $panel, int|string|null $tenant, Collection $supporters): Collection
    {
        $query = $this->pluginOf($panel)->getRequestersQuery();
        $model = $query->getModel();

        return $query
            ->whereKeyNot($supporters->modelKeys())
            ->when($tenant !== null && Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $this->getTenantKey()),
                fn ($query) => $query->where($model->qualifyColumn($this->getTenantKey()), $tenant))
            ->limit(3)
            ->get();
    }

    protected function pluginOf(Panel $panel): TicketPlugin
    {
        /** @var TicketPlugin */
        return $panel->getPlugin(TicketPlugin::$id);
    }
}
