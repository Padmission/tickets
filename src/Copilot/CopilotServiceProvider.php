<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Padmission\Tickets\Copilot\Commands\InstallCommand;
use Padmission\Tickets\Copilot\Commands\MakeCopilotToolCommand;
use Padmission\Tickets\Copilot\Livewire\ConversationSidebar;
use Padmission\Tickets\Copilot\Livewire\CopilotButton;
use Padmission\Tickets\Copilot\Livewire\CopilotChat;
use Padmission\Tickets\Copilot\Services\ConversationManager;
use Padmission\Tickets\Copilot\Services\ExportService;
use Padmission\Tickets\Copilot\Services\RateLimitService;
use Padmission\Tickets\Copilot\Services\ToolRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CopilotServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-copilot';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommand(InstallCommand::class)
            ->hasCommand(MakeCopilotToolCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/filament-copilot.php', 'filament-copilot');

        $this->app->singleton(RateLimitService::class);
        $this->app->singleton(ConversationManager::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(ExportService::class);
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__.'/views', 'filament-copilot');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'filament-copilot');
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $this->publishes([
            __DIR__.'/config/filament-copilot.php' => config_path('filament-copilot.php'),
        ], 'filament-copilot-config');

        $this->publishes([
            __DIR__.'/migrations' => database_path('migrations'),
        ], 'filament-copilot-migrations');

        $this->publishes([
            __DIR__.'/dist' => public_path('vendor/filament-copilot'),
        ], 'filament-copilot-assets');

        $this->publishes([
            __DIR__.'/stubs' => base_path('stubs/filament-copilot'),
        ], 'filament-copilot-stubs');

        FilamentAsset::register([
            Css::make('filament-copilot', asset('vendor/filament-copilot/filament-copilot.css')),
            Js::make('filament-copilot', asset('vendor/filament-copilot/filament-copilot.js')),
        ], 'padmission/tickets-copilot');

        if (class_exists(Livewire::class) && $this->app->bound('livewire')) {
            Livewire::component('filament-copilot-chat', CopilotChat::class);
            Livewire::component('filament-copilot-button', CopilotButton::class);
            Livewire::component('filament-copilot-sidebar', ConversationSidebar::class);
        }
    }
}
