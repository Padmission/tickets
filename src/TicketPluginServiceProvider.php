<?php

namespace Padmission\Tickets;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TicketPluginServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('padmission-tickets')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            // TODO: Refactor into install command. For now keep this during development.
            ->discoversMigrations()
            ->runsMigrations();
    }
}
