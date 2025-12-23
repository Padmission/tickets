<?php

namespace Padmission\Tickets;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Console\Commands\SeedTicketsCommand;
use Padmission\Tickets\Services\EmailLogoService;
use Padmission\Tickets\Services\EmailStyleService;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketUrlService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

define('TICKET_PLUGIN_DIR', __DIR__.'/..');

class TicketPluginServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('padmission-tickets')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasRoutes('api')
            ->hasCommand(SeedTicketsCommand::class)
            ->discoversMigrations();
    }

    public function bootingPackage(): void
    {
        if (config('padmission-tickets.run_migrations', true)) {
            $this->package->runsMigrations();
        }

        if (app()->environment('local')) {
            $this->loadRoutesFrom("{$this->package->basePath('/../routes/')}dev.php");
        }

        $this->registerAssets();
        $this->registerBrowserSync();
    }

    public function packageBooted(): void
    {
        $this->bootEventListeners();
    }

    public function packageRegistered(): void
    {
        $this->registerServices();
    }

    /**
     * Register services with the container
     */
    protected function registerServices(): void
    {
        $this->app->singleton(TicketActivityService::class);
        $this->app->singleton(EmailLogoService::class);
        $this->app->singleton(EmailStyleService::class);
        $this->app->singleton(TicketUrlService::class);
        $this->app->singleton(NotificationRecipientService::class);
    }

    private function registerAssets(): void
    {
        $assets = [
            Css::make('chat-component', __DIR__.'/../resources/css/chat-component.css')->loadedOnRequest(),
            Css::make('chat-widget', __DIR__.'/../resources/css/chat-widget.css')->loadedOnRequest(),
            Css::make('tickets', __DIR__.'/../resources/css/tickets.css'),

            Js::make('chat-widget', __DIR__.'/../dist/chat-widget.js')->loadedOnRequest(),
        ];

        if (! $this->isDevMode()) {
            FilamentAsset::register($assets, package: 'padmission/tickets');

            return;
        }

        foreach ($assets as $asset) {
            /**
             * @var Css|Js $asset
             */
            $asset->package('padmission/tickets');

            Route::get($asset->getRelativePublicPath(), static function () use ($asset) {
                if (file_exists($asset->getPath())) {
                    return response()->file($asset->getPath(), ['Content-Type' => 'text/'.($asset instanceof Css ? 'css' : 'javascript')]);
                }

                return response('', 404, ['Content-Type' => 'text/plain']);
            });

            $timestamp = file_exists($asset->getPath()) ? filemtime($asset->getPath()) : time();

            FilamentAsset::register([
                ($asset::class)::make(
                    id: $asset->getId(),
                    path: url($asset->getRelativePublicPath()."?t={$timestamp}")
                )->loadedOnRequest($asset->isLoadedOnRequest()),
            ], package: 'padmission/tickets');
        }
    }

    private function registerBrowserSync(): void
    {
        if (! $this->isDevMode()) {
            return;
        }

        /* @phpstan-ignore-next-line */
        $port = env('BROWSERSYNC_PORT') ?? 3000;

        FilamentAsset::register([
            Js::make('browser-sync', "http://localhost:$port/browser-sync/browser-sync-client.js"),
        ], package: 'padmission-tickets');
    }

    private function isDevMode(): bool
    {
        return file_exists(__DIR__.'/../dist/.hot');
    }

    protected function bootEventListeners(): void
    {
        $listeners = config('padmission-tickets.event-listeners', []);

        foreach ($listeners as $event => $eventListeners) {
            foreach (Arr::wrap($eventListeners) as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
