<?php

declare(strict_types=1);

namespace Padmission\Tickets\Copilot\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Copilot\Models\CopilotMessage;
use Padmission\Tickets\Copilot\Models\Scopes\CopilotTenantScope;
use Padmission\Tickets\Copilot\Support\CopilotTenantContext;

trait HasCopilotTenant
{
    protected static function bootHasCopilotTenant(): void
    {
        static::addGlobalScope(new CopilotTenantScope);

        static::creating(function (Model $model): void {
            static::fillCopilotTenant($model);
        });
    }

    public function scopeWithoutCopilotTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CopilotTenantScope::class);
    }

    public function scopeForCopilotTenant(Builder $query, ?array $tenant): Builder
    {
        $query->withoutGlobalScope(CopilotTenantScope::class);

        if ($tenant === null) {
            return $query
                ->whereNull($query->getModel()->qualifyColumn('tenant_type'))
                ->whereNull($query->getModel()->qualifyColumn('tenant_id'));
        }

        return $query
            ->where($query->getModel()->qualifyColumn('tenant_type'), $tenant['type'])
            ->where($query->getModel()->qualifyColumn('tenant_id'), $tenant['id']);
    }

    protected static function fillCopilotTenant(Model $model): void
    {
        if ($model->getAttribute('tenant_id')) {
            return;
        }

        $tenant = static::resolveCopilotTenantFromCascade($model)
            ?? CopilotTenantContext::current();

        if ($tenant === null) {
            return;
        }

        $model->setAttribute('tenant_type', $tenant['type']);
        $model->setAttribute('tenant_id', $tenant['id']);
    }

    /**
     * Walk the conversation/message FK chain to inherit tenant from a parent record.
     * Child rows (messages, tool_calls, audit_logs, token_usages) shouldn't drift
     * from their conversation's tenant even if the current resolver returns something
     * different.
     *
     * @return array{type: string, id: int|string}|null
     */
    protected static function resolveCopilotTenantFromCascade(Model $model): ?array
    {
        if ($model->relationLoaded('conversation') && $model->getRelation('conversation')) {
            return static::tenantArrayFor($model->getRelation('conversation'));
        }

        if ($model->getAttribute('conversation_id')) {
            $row = CopilotConversation::query()
                ->withoutGlobalScope(CopilotTenantScope::class)
                ->whereKey($model->getAttribute('conversation_id'))
                ->first(['tenant_type', 'tenant_id']);

            return $row ? static::tenantArrayFor($row) : null;
        }

        if ($model->relationLoaded('message') && $model->getRelation('message')) {
            return static::tenantArrayFor($model->getRelation('message'));
        }

        if ($model->getAttribute('message_id')) {
            $row = CopilotMessage::query()
                ->withoutGlobalScope(CopilotTenantScope::class)
                ->whereKey($model->getAttribute('message_id'))
                ->first(['tenant_type', 'tenant_id']);

            return $row ? static::tenantArrayFor($row) : null;
        }

        return null;
    }

    /**
     * @return array{type: string, id: int|string}|null
     */
    protected static function tenantArrayFor(Model $source): ?array
    {
        $type = $source->getAttribute('tenant_type');
        $id = $source->getAttribute('tenant_id');

        if (! $type || ! $id) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }
}
