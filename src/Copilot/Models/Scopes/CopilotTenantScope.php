<?php

declare(strict_types=1);

namespace Padmission\Tickets\Copilot\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Padmission\Tickets\Copilot\Support\CopilotTenantContext;

class CopilotTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = CopilotTenantContext::current();

        if ($tenant === null) {
            return;
        }

        $builder
            ->where($model->qualifyColumn('tenant_type'), $tenant['type'])
            ->where($model->qualifyColumn('tenant_id'), $tenant['id']);
    }
}
