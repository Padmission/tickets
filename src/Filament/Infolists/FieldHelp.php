<?php

namespace Padmission\Tickets\Filament\Infolists;

use Closure;
use Filament\Facades\Filament;
use Filament\Infolists\Components\Entry;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Padmission\Tickets\TicketPlugin;

/*
 * Explains an entry in the way the panel asks for (see TicketPlugin::fieldHelp()),
 * so hosts adding their own entries, such as an organization, match the rest.
 */
class FieldHelp
{
    /**
     * @template T of Entry
     *
     * @param  T  $entry
     * @return T
     */
    public static function apply(Entry $entry, string|Closure $label, string|Closure $help): Entry
    {
        return $entry
            ->label(fn (Entry $component): string|Htmlable => static::style() === TicketPlugin::FIELD_HELP_TOOLTIP
                ? static::label($component->evaluate($label), $component->evaluate($help))
                : $component->evaluate($label))
            ->hintColor('gray')
            ->hintIcon(fn (): ?Heroicon => static::style() === TicketPlugin::FIELD_HELP_INLINE ? Heroicon::OutlinedQuestionMarkCircle : null, tooltip: $help);
    }

    public static function style(): string
    {
        return TicketPlugin::find(Filament::getCurrentPanel()?->getId())?->getFieldHelp()
            ?? TicketPlugin::FIELD_HELP_TOOLTIP;
    }

    /*
     * The label itself carries the tooltip and takes keyboard focus, and the
     * explanation is also in the page for screen readers, so it is not
     * reachable by hovering alone.
     */
    public static function label(string $label, string $help): Htmlable
    {
        $id = 'pad-ti-help-'.Str::random(8);

        return new HtmlString(sprintf(
            '<span class="pad-ti-help-label" tabindex="0" aria-describedby="%1$s" x-data x-tooltip="{ content: %2$s, theme: $store.theme }">%3$s</span><span id="%1$s" class="fi-sr-only">%4$s</span>',
            $id,
            e(json_encode($help, JSON_HEX_APOS | JSON_HEX_QUOT)),
            e($label),
            e($help),
        ));
    }
}
