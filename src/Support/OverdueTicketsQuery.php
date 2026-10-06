<?php

namespace Padmission\Tickets\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;

final class OverdueTicketsQuery
{
    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function apply(Builder $query): Builder
    {
        $now = CarbonImmutable::now();
        $days = (int) config('padmission-tickets.overdue.business_days', 1);
        $fallbackTimezone = config('padmission-tickets.overdue.timezone') ?? config('app.timezone');
        $groups = self::tenantTimezones();
        $knownIds = array_merge([], ...array_values($groups));

        // MAX is correlated by ticket_id, keeping both list and card in SQL:
        // a new requester message resets the clock; notes and support updates do not.
        $messages = Relation::noConstraints(fn () => $query->getModel()->ticketActivities())->getQuery();
        $messages->whereColumn($messages->qualifyColumn('ticket_id'), $query->qualifyColumn('id'))
            ->where('type', ActivityType::Message)
            ->where('sender', ActivitySender::User)
            ->whereNull($messages->qualifyColumn('deleted_at'))
            ->reorder()->selectRaw('max('.$messages->qualifyColumn('created_at').')');

        $messages = $messages->toBase();
        $olderThan = fn (Builder $query, string $timezone): Builder => $query->whereRaw(
            '('.$messages->toSql().') < ?',
            [...$messages->getBindings(), BusinessDayCutoff::before($now, $days, $timezone)->setTimezone(config('app.timezone'))->toDateTimeString()],
        );

        return $query->open()->where($query->qualifyColumn('turn'), Turn::Supporter)
            ->where(function (Builder $query) use ($groups, $knownIds, $olderThan, $fallbackTimezone): void {
                $query->where(function (Builder $query) use ($knownIds, $olderThan, $fallbackTimezone): void {
                    if ($knownIds !== []) {
                        $query->where(fn (Builder $query): Builder => $query
                            ->whereNotIn($query->qualifyColumn('tenant_id'), $knownIds)
                            ->orWhereNull($query->qualifyColumn('tenant_id')));
                    }

                    $olderThan($query, $fallbackTimezone);
                });

                foreach ($groups as $timezone => $ids) {
                    $query->orWhere(function (Builder $query) use ($olderThan, $timezone, $ids): void {
                        $olderThan($query->whereIn($query->qualifyColumn('tenant_id'), $ids), $timezone);
                    });
                }
            });
    }

    /**
     * Look up only organization keys and timezones, once per request. The ticket
     * query retains its host scopes; the lookup can resolve other organizations
     * on a central support panel without loading any tickets or people.
     *
     * @return array<string, list<int|string>>
     */
    protected static function tenantTimezones(): array
    {
        return once(function (): array {
            $model = config('padmission-tickets.tenancy.tenancy_model');

            if (! config('padmission-tickets.tenancy.enabled')
                || config('padmission-tickets.overdue.timezone') !== null
                || ! is_string($model) || ! class_exists($model)) {
                return [];
            }

            $tenant = new $model;

            if (! $tenant->getConnection()->getSchemaBuilder()->hasColumn($tenant->getTable(), 'timezone')) {
                return [];
            }

            $groups = [];
            $validTimezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);

            foreach ($tenant->newQueryWithoutScopes()->pluck('timezone', $tenant->getKeyName()) as $id => $timezone) {
                if (is_string($timezone) && in_array($timezone, $validTimezones, true)) {
                    $groups[$timezone][] = $id;
                }
            }

            return $groups;
        });
    }
}
