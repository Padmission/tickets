<?php

namespace Padmission\Tickets\Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SeedForTenants
{
    protected static ?string $tenantKey = null;

    protected function getTenantKey(): string
    {
        return static::$tenantKey ??= str(config('padmission-tickets.tenancy.tenancy_model'))
            ->classBasename()
            ->snake()
            ->append('_id')
            ->toString();
    }

    protected function addTenantColumn(array $row, int|string|null $tenantId): array
    {
        if ($tenantId === null) {
            return $row;
        }

        $row[$this->getTenantKey()] = $tenantId;

        return $row;
    }

    /**
     * Whether this tenant already has rows in this panel. Checked per pair rather
     * than once for the whole table, so a tenant or panel added after the first
     * seed still gets its defaults, and host global scopes (tenant, current
     * panel, soft deletes) cannot hide rows that exist.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function hasRowsFor(string $modelClass, string $panelId, int|string|null $tenantId): bool
    {
        return $modelClass::query()
            ->withoutGlobalScopes()
            ->where('panel', $panelId)
            ->when($tenantId !== null, fn ($query) => $query->where($this->getTenantKey(), $tenantId))
            ->exists();
    }

    protected function getTenants(int|string|null $tenantId): array
    {
        $tenancyEnabled = config('padmission-tickets.tenancy.enabled', false);

        if (! $tenancyEnabled) {
            return [null];
        }

        if ($tenantId) {
            return [$tenantId];
        }

        $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');

        return $tenantModelClass::all()
            ->map(fn ($tenantModel) => $tenantModel->getKey())
            ->toArray();
    }
}
