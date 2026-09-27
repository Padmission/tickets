<?php

namespace Padmission\Tickets\Services;

use Illuminate\Support\Collection;
use Padmission\Tickets\Models\Ticket;

/*
 * An escalation is named by the people whose tickets it is about, because a
 * ticket about other tickets is otherwise hard to place.
 */
class EscalationSummary
{
    protected const KEY = 'padmission-tickets::tickets.resources.tickets';

    public static function about(Ticket $escalation): string
    {
        /** @var Collection<int, Ticket> $originals */
        $originals = $escalation->childTickets;

        if ($originals->isEmpty()) {
            return __(self::KEY.'.escalation_about_none');
        }

        $about = __(self::KEY.'.escalation_about', ['originals' => static::originals($originals)]);
        $closed = $originals->whereNotNull('closed_at')->count();

        return match ($closed) {
            0 => $about,
            $originals->count() => __(self::KEY.'.escalation_about_all_closed', ['about' => $about]),
            default => __(self::KEY.'.escalation_about_some_closed', ['about' => $about, 'closed' => $closed, 'total' => $originals->count()]),
        };
    }

    /**
     * @param  Collection<int, Ticket>  $originals
     */
    public static function originals(Collection $originals): string
    {
        $requesters = static::requesters($originals);

        if ($requesters === null) {
            return trans_choice(self::KEY.'.escalation_originals.unnamed', $originals->count(), ['count' => $originals->count()]);
        }

        return match ($requesters->count()) {
            1 => trans_choice(self::KEY.'.escalation_originals.one', $requesters[0]['count'], ['name' => $requesters[0]['name'], 'count' => $requesters[0]['count']]),
            2 => __(self::KEY.'.escalation_originals.two', ['first' => $requesters[0]['name'], 'second' => $requesters[1]['name']]),
            default => __(self::KEY.'.escalation_originals.many', ['first' => $requesters[0]['name'], 'count' => $requesters->count() - 1]),
        };
    }

    /*
     * The one person every original came from, so a page can say that they,
     * rather than "the requesters", never see the escalation.
     *
     * @param  Collection<int, Ticket>  $originals
     */
    public static function soleRequester(Collection $originals): ?string
    {
        $requesters = static::requesters($originals);

        return $requesters?->count() === 1 ? $requesters[0]['name'] : null;
    }

    /**
     * Each person once, in the order of their first ticket, so two tickets
     * from the same person do not name them twice. Null when any original
     * has no name to show.
     *
     * @param  Collection<int, Ticket>  $originals
     * @return Collection<int, array{name: string, count: int}>|null
     */
    protected static function requesters(Collection $originals): ?Collection
    {
        $sorted = $originals->sortBy('id')->values();

        if ($sorted->isEmpty() || $sorted->contains(fn (Ticket $original): bool => blank($original->requesterName()))) {
            return null;
        }

        return $sorted
            ->groupBy(fn (Ticket $original): string => filled($original->submitter_id)
                ? 'user:'.$original->submitter_id
                : 'guest:'.mb_strtolower((string) ($original->submitter_data->email ?? $original->requesterName())))
            ->map(fn (Collection $tickets): array => ['name' => (string) $tickets->first()->requesterName(), 'count' => $tickets->count()])
            ->values();
    }
}
