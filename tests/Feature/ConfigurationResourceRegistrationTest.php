<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Padmission\Tickets\Filament\Resources\Dispositions\DispositionResource;
use Padmission\Tickets\Filament\Resources\Priorities\PriorityResource;
use Padmission\Tickets\Filament\Resources\Statuses\StatusResource;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * A panel that only answers other panels' tickets has no organization of its
 * own to configure. A host clusters these screens elsewhere, so registering
 * them there routes to a cluster the panel never registered.
 */
function panelWithPlugin(string $id, bool $configuration): Panel
{
    $plugin = TicketPlugin::make()
        ->allSupportersQuery(fn () => User::query())
        ->registerResources()
        ->registerConfigurationResources($configuration);

    $panel = Panel::make()->id($id)->path($id)->plugin($plugin);

    Filament::registerPanel($panel);
    $panel->register();

    return $panel;
}

it('offers the configuration resources by default', function () {
    $panel = panelWithPlugin('configures-its-own', true);

    expect($panel->getResources())
        ->toContain(TicketResource::class)
        ->toContain(StatusResource::class)
        ->toContain(PriorityResource::class)
        ->toContain(DispositionResource::class);
});

it('leaves them off a panel that does not configure its own', function () {
    $panel = panelWithPlugin('answers-only', false);

    expect($panel->getResources())
        ->toContain(TicketResource::class)
        ->not->toContain(StatusResource::class)
        ->not->toContain(PriorityResource::class)
        ->not->toContain(DispositionResource::class);
});
