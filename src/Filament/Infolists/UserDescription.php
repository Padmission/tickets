<?php

namespace Padmission\Tickets\Filament\Infolists;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/*
 * A person can hold many roles, and listing them all beside a name crowds
 * narrow columns, so only the first is shown and the rest sit in a tooltip.
 */
class UserDescription
{
    /**
     * @param  list<string>  $parts
     */
    public static function render(array $parts): ?Htmlable
    {
        if ($parts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return new HtmlString(e($parts[0]));
        }

        $all = implode(', ', $parts);

        return new HtmlString(sprintf(
            '%s <span class="pad-ti-more" tabindex="0" x-data x-tooltip="{ content: %s, theme: $store.theme }" aria-label="%s">+%d</span>',
            e($parts[0]),
            e(json_encode($all, JSON_HEX_APOS | JSON_HEX_QUOT)),
            e($all),
            count($parts) - 1,
        ));
    }

    /**
     * @param  list<string>  $parts
     */
    public static function plain(array $parts): ?string
    {
        return match (count($parts)) {
            0 => null,
            1 => $parts[0],
            default => $parts[0].' +'.(count($parts) - 1),
        };
    }
}
