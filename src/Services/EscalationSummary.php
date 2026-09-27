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
        $names = $originals->sortBy('id')->map(fn (Ticket $original): ?string => $original->requesterName())->values();

        if ($names->contains(fn (?string $name): bool => blank($name))) {
            return trans_choice(self::KEY.'.escalation_originals.unnamed', $names->count(), ['count' => $names->count()]);
        }

        return match ($names->count()) {
            1 => __(self::KEY.'.escalation_originals.one', ['name' => $names[0]]),
            2 => __(self::KEY.'.escalation_originals.two', ['first' => $names[0], 'second' => $names[1]]),
            default => __(self::KEY.'.escalation_originals.many', ['first' => $names[0], 'count' => $names->count() - 1]),
        };
    }
}
