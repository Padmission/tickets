<?php

namespace Padmission\Tickets\Database\Seeders\Concerns;

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
