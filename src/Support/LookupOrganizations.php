<?php

namespace Padmission\Tickets\Support;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\TicketPlugin;

class LookupOrganizations
{
    public const COLUMN = 'tenant_id';

    /**
     * @param  Builder<Model>  $lookups
     */
    public static function spanned(Builder $lookups): bool
    {
        if (! static::columnExists($lookups->getModel())) {
            return false;
        }

        // Counting distinct values would skip a shared, organization-less set's
        // empty one, leaving a host that pairs it with an organization's own set
        // looking single-organization.
        $first = (clone $lookups)->reorder()->value(static::COLUMN);

        return (clone $lookups)->where(fn (Builder $other): Builder => $first === null
            ? $other->whereNotNull(static::COLUMN)
            : $other->whereNull(static::COLUMN)->orWhere(static::COLUMN, '!=', $first))->exists();
    }

    public static function columnExists(Model $model): bool
    {
        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), static::COLUMN);
    }

    public static function column(): TextColumn
    {
        return TextColumn::make(static::COLUMN)
            ->label(__('padmission-tickets::tickets.resources.organization'))
            ->formatStateUsing(fn (mixed $state): string => static::names()[$state] ?? __('padmission-tickets::tickets.resources.shared_organization'))
            ->sortable();
    }

    public static function filter(): SelectFilter
    {
        return SelectFilter::make(static::COLUMN)
            ->label(__('padmission-tickets::tickets.resources.organization'))
            ->options(fn (): array => static::names())
            ->searchable()
            ->preload();
    }

    /**
     * @return array<int|string, string>
     */
    public static function names(): array
    {
        return once(function (): array {
            $model = config('padmission-tickets.tenancy.tenancy_model');
            $query = TicketPlugin::get()->getTicketTenantsQuery()
                ?? (is_string($model) && class_exists($model) ? $model::query() : null);

            if ($query === null) {
                return [];
            }

            return $query
                ->orderBy($query->qualifyColumn('name'))
                ->pluck($query->qualifyColumn('name'), $query->getModel()->getQualifiedKeyName())
                ->all();
        });
    }
}
