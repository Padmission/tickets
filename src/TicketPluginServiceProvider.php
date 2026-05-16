<?php

namespace Padmission\Tickets;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Console\Commands\SeedTicketsCommand;
use Padmission\Tickets\Listeners\TicketNotificationListener;
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
            ->hasCommand(SeedTicketsCommand::class)
            ->discoversMigrations();
    }

    public function bootingPackage(): void
    {
        if (config('padmission-tickets.run_migrations', true)) {
            $this->package->runsMigrations();
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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
        $this->app->extend(Markdown::class, function (Markdown $markdown, $app) {
            $invaded = invade($markdown);

            $markdown->loadComponentsFrom([
                ...$invaded->componentPaths, // @phpstan-ignore-line (intentionally accessing protected property via spatie/invade)
                __DIR__.'/../resources/views/mail-components',
            ]);

            return $markdown;
        });

        $this->registerServices();
    }

    /**
     * Register services with the container
     */
    protected function registerServices(): void
    {
        $this->app->singleton(TicketActivityService::class);
        $this->app->singleton(TicketUrlService::class);
        $this->app->singleton(NotificationRecipientService::class);
    }

    private function registerAssets(): void
    {
        $ticketsCssPath = __DIR__.'/../resources/css/tickets.css';

        $assets = [
            Css::make('tickets', $ticketsCssPath)
                ->html(fn (): string => asset('css/padmission/tickets/tickets.css').'?t='.(file_exists($ticketsCssPath) ? filemtime($ticketsCssPath) : time())),
            Js::make('support-panel-stream', __DIR__.'/../resources/js/support-panel-stream.js')->loadedOnRequest(),
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
        $events = [
            Events\TicketActivityEvent::class,
            Events\TicketAssignedEvent::class,
            Events\TicketClosedEvent::class,
        ];

        foreach ($events as $event) {
            Event::listen($event, TicketNotificationListener::class);
        }
    }
}
