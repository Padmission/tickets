<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot;

use Livewire\Livewire;
use Padmission\Tickets\Copilot\Commands\InstallCommand;
use Padmission\Tickets\Copilot\Commands\MakeCopilotToolCommand;
use Padmission\Tickets\Copilot\Services\AiFeedbackExportService;
use Padmission\Tickets\Copilot\Services\ConversationManager;
use Padmission\Tickets\Copilot\Services\EscalationDetector;
use Padmission\Tickets\Copilot\Services\ExportService;
use Padmission\Tickets\Copilot\Services\RateLimitService;
use Padmission\Tickets\Copilot\Services\ToolRegistry;
use Padmission\Tickets\Filament\Livewire\Support\SupportButton;
use Padmission\Tickets\Filament\Livewire\Support\SupportPanel;
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
        $this->app->singleton(AiFeedbackExportService::class);
        $this->app->scoped(EscalationDetector::class);
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
            __DIR__.'/stubs' => base_path('stubs/filament-copilot'),
        ], 'filament-copilot-stubs');

        if (class_exists(Livewire::class) && $this->app->bound('livewire')) {
            Livewire::component('padmission-tickets.support-button', SupportButton::class);
            Livewire::component('padmission-tickets.support-panel', SupportPanel::class);
        }
    }
}
