<?php

namespace Padmission\Tickets;

use Dotenv\Dotenv;
use Exception;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Console\Commands\SeedTicketsCommand;
use Padmission\Tickets\Models\Ticket;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

define('TICKET_PLUGIN_DIR', __DIR__.'/..');

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
            ->hasCommand(SeedTicketsCommand::class)
            ->discoversMigrations();
    }

    public function bootingPackage(): void
    {
        if (config('padmission-tickets.run_migrations', true)) {
            $this->package->runsMigrations();
        }

        $this->ensurePolicyIsRegistered();

        if ($this->isDevMode()) {
            $this->devConfig = Dotenv::parse(file_get_contents(__DIR__.'/../.env'));
        }

        $this->registerCssFiles();
        $this->registerBrowserSync();

    }

    public function packageBooted(): void
    {
        $this->bootEventListeners();

    }

    public function packageRegistered(): void {}

    private function ensurePolicyIsRegistered(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $policy = Gate::getPolicyFor(
            TicketPlugin::resolveModelClass(Ticket::class)
        );

        if ($policy === null) {
            throw new Exception('Register a TicketPolicy via Gate::policy() facade in a ServiceProvider::register() method.');
        }

        /*
         * We want to make sure users register a policy with certain methods.
         * Because PHPs parameter types are contravariant we don't want to
         * provide a class or interface to implement because we cannot provide
         * type hints like `Authenticatable` in the methods leading to devs not
         * able to define their own type hints as well.
         */
        $requiredMethods = [
            'viewAny',
            'create',
            'manage',
            'escalate',
            'delete',
        ];

        foreach ($requiredMethods as $method) {
            if (! method_exists($policy, $method)) {
                throw new Exception("The policy should implement '$method()' method");
            }
        }
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

    protected function bootEventListeners(): void
    {
        $listeners = config('padmission-tickets.event-listeners', []);

        foreach ($listeners as $event => $eventListeners) {
            if (! is_array($eventListeners)) {
                $eventListeners = [$eventListeners];
            }

            foreach ($eventListeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
