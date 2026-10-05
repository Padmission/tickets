<?php

namespace Padmission\Tickets;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Padmission\Tickets\Console\Commands\SeedTicketsCommand;
use Padmission\Tickets\Listeners\TicketNotificationListener;
use Padmission\Tickets\Livewire\CopilotTicketPanel;
use Padmission\Tickets\Services\CopilotTicketService;
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

        if (config('padmission-tickets.staff_api.enabled')) {
            $this->loadRoutesFrom("{$this->package->basePath('/../routes/')}staff-api.php");
        }

        $this->registerAssets();
        $this->registerBrowserSync();
    }

    public function packageBooted(): void
    {
        $this->bootEventListeners();
        $this->registerLivewireComponents();
        $this->registerRateLimiters();
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('padmission-tickets-writes', fn (Request $request): Limit => Limit::perMinute((int) config('padmission-tickets.api.writes_per_minute', 60))
            ->by('padmission-tickets-writes:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
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
        $this->app->singleton(CopilotTicketService::class);
        $this->app->singleton(TicketActivityService::class);
        $this->app->singleton(TicketUrlService::class);
        $this->app->singleton(NotificationRecipientService::class);
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('padmission-tickets-copilot-panel', CopilotTicketPanel::class);
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
        $events = [
            Events\TicketActivityEvent::class,
            Events\TicketAssignedEvent::class,
            Events\TicketClosedEvent::class,
            Events\TicketCreatedEvent::class,
            Events\TicketHandedOverEvent::class,
            Events\TicketReopenedEvent::class,
        ];

        foreach ($events as $event) {
            Event::listen($event, TicketNotificationListener::class);
        }
    }
}
