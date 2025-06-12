<?php

namespace Padmission\Tickets\Filament\Resources\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\TicketPlugin;

trait HasResourceConfiguration
{
    public static Closure|string|null $getModelLabelUsing = null;

    public static Closure|string|null $getPluralModelLabelUsing = null;

    public static Closure|string|null $getNavigationGroupUsing = null;

    public static Closure|string|Htmlable|null $getNavigationIconUsing = null;

    public static function configure(
        Closure|string|null $modelLabel = null,
        Closure|string|null $pluralModelLabel = null,
        Closure|string|null $navigationGroup = null,
        Closure|string|Htmlable|null $navigationIcon = null,
    ): void {
        static::$getModelLabelUsing = $modelLabel;
        static::$getPluralModelLabelUsing = $pluralModelLabel;
        static::$getNavigationGroupUsing = $navigationGroup;
        static::$getNavigationIconUsing = $navigationIcon;
    }

    public static function getModel(): string
    {
        return TicketPlugin::resolveModelClass(static::$model);
    }

    public static function getModelLabel(): string
    {
        if (static::$getModelLabelUsing) {
            return value(static::$getModelLabelUsing);
        }

        return __('padmission-tickets::tickets.resources.'.static::$slug.'.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        if (static::$getPluralModelLabelUsing) {
            return value(static::$getPluralModelLabelUsing);
        }

        return __('padmission-tickets::tickets.resources.'.static::$slug.'.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        if (static::$getNavigationGroupUsing) {
            return value(static::$getNavigationGroupUsing);
        }

        return __('padmission-tickets::tickets.resources.navigation_group');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        if (static::$getNavigationIconUsing) {
            return value(static::$getNavigationIconUsing);
        }

        return new HtmlString(file_get_contents(TICKET_PLUGIN_DIR.'/resources/icons/'.static::$slug.'.svg'));
    }
}
