<?php

namespace Padmission\Tickets;

use Dotenv\Dotenv;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Models\Policies\TicketPolicy;
use Padmission\Tickets\Models\Ticket;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TicketPluginServiceProvider extends PackageServiceProvider
{
    private array $devConfig;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('padmission-tickets')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasRoutes('api')
            // TODO: Refactor into install command. For now keep this during development.
            ->discoversMigrations()
            ->runsMigrations();

        Gate::policy(
            Ticket::class,
            TicketPolicy::class
        );
    }

    public function bootingPackage(): void
    {
        if ($this->isDevMode()) {
            $this->devConfig = Dotenv::parse(file_get_contents(__DIR__.'/../.env'));
        }

        $this->registerCssFiles();
        $this->registerBrowserSync();
    }

    private function registerCssFiles(): void
    {
        $files = [
            __DIR__.'/../resources/css/chat-component.css',
            __DIR__.'/../dist/chat-component.js',

            __DIR__.'/../resources/css/chat-widget.css',
            __DIR__.'/../dist/chat-widget.js',
        ];

        foreach ($files as $filepath) {
            $name = pathinfo($filepath, PATHINFO_FILENAME);
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            $type = $extension === 'css' ? 'css' : 'javascript';
            $assetClass = $extension === 'css' ? Css::class : Js::class;

            if (! $this->isDevMode()) {
                FilamentAsset::register([
                    $assetClass::make($name, $filepath)->loadedOnRequest(),
                ], package: 'padmission-tickets');

                continue;
            }

            Route::get("{$extension}/padmission-tickets/{$name}.{$extension}", function () use ($filepath, $type) {
                if (file_exists($filepath)) {
                    return response()->file($filepath, ['Content-Type' => 'text/'.$type]);
                }

                return response('', 404, ['Content-Type' => 'text/'.$type]);
            });

            $timestamp = file_exists($filepath) ? filemtime($filepath) : time();

            FilamentAsset::register([
                $assetClass::make(
                    $name,
                    url("{$extension}/padmission-tickets/$name.$extension?t={$timestamp}")
                )->loadedOnRequest(),
            ], package: 'padmission-tickets');
        }
    }

    private function registerBrowserSync(): void
    {
        if (! $this->isDevMode()) {
            return;
        }

        $port = $this->devConfig['BROWSERSYNC_PORT'] ?? 3000;

        FilamentAsset::register([
            Js::make('browser-sync', "http://localhost:$port/browser-sync/browser-sync-client.js"),
        ], package: 'padmission-tickets');
    }

    private function isDevMode(): bool
    {
        return file_exists(__DIR__.'/../dist/.hot');
    }
}
