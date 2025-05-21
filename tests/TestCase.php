<?php

namespace Padmission\Tickets\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\InteractsWithPest;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\TicketPluginServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

#[WithMigration]
class TestCase extends \Orchestra\Testbench\TestCase
{
    use InteractsWithPest;
    use InteractsWithViews;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,

            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            TablesServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            WidgetsServiceProvider::class,
            NotificationsServiceProvider::class,

            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,

            TicketPluginServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        config()->set('padmission-tickets.models.'.Authenticatable::class, \Padmission\Tickets\Tests\User::class);

        Filament::registerPanel(fn () => Panel::make()
            ->default()
            ->id('test')
            ->path('test')
            ->plugin(TicketPlugin::make()),
        );
    }

    public function replacePlugin(TicketPlugin $plugin): void
    {
        Filament::getPanel('test')->plugin(
            $plugin,
        );
    }
}
