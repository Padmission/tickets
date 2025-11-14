<?php

namespace Padmission\Tickets\Filament\Resources\Concerns;

use Closure;
use Filament\Pages\Enums\SubNavigationPosition;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\TicketPlugin;

trait HasResourceConfiguration
{
    public static Closure|string|null $getModelLabelUsing = null;

    public static Closure|string|null $getPluralModelLabelUsing = null;

    public static Closure|string|null $getNavigationGroupUsing = null;

    public static Closure|string|Htmlable|null $getNavigationIconUsing = null;

    public static Closure|int|null $getNavigationSortUsing = null;

    public static Closure|string|null $getNavigationParentItemUsing = null;

    public static Closure|SubNavigationPosition|null $getSubNavigationPositionUsing = null;

    public static Closure|string|null $getClusterUsing = null;

    public static function configure(
        Closure|string|null $modelLabel = null,
        Closure|string|null $pluralModelLabel = null,
        Closure|string|null $navigationGroup = null,
        Closure|string|Htmlable|null $navigationIcon = null,
        Closure|int|null $navigationSort = null,
        Closure|string|null $navigationParentItem = null,
        Closure|SubNavigationPosition|null $subNavigationPosition = null,
        Closure|string|null $cluster = null,
    ): void {
        static::$getModelLabelUsing = $modelLabel;
        static::$getPluralModelLabelUsing = $pluralModelLabel;
        static::$getNavigationGroupUsing = $navigationGroup;
        static::$getNavigationIconUsing = $navigationIcon;
        static::$getNavigationSortUsing = $navigationSort;
        static::$getNavigationParentItemUsing = $navigationParentItem;
        static::$getSubNavigationPositionUsing = $subNavigationPosition;
        static::$getClusterUsing = $cluster;
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

    public static function getNavigationSort(): ?int
    {
        if (static::$getNavigationSortUsing) {
            return value(static::$getNavigationSortUsing);
        }

        return null;
    }

    public static function getNavigationParentItem(): ?string
    {
        if (isset(static::$getNavigationParentItemUsing)) {
            return value(static::$getNavigationParentItemUsing);
        }

        return TicketResource::getNavigationLabel();
    }

    public static function getCluster(): ?string
    {
        if (isset(static::$getClusterUsing)) {
            return value(static::$getClusterUsing);
        }

        return self::$cluster;
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        if (isset(static::$getSubNavigationPositionUsing)) {
            return value(static::$getSubNavigationPositionUsing);
        }

        return self::$subNavigationPosition ?? SubNavigationPosition::Start;
    }
}
