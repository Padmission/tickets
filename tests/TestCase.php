<?php

namespace Padmission\Tickets\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Closure;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Livewire\Features\SupportTesting\Testable;
use Livewire\LivewireServiceProvider;
use Mpbarlow\LaravelQueueDebouncer\ServiceProvider as LaravelQueueDebounceServiceProvider;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\InteractsWithPest;
use Padmission\Tickets\Copilot\CopilotServiceProvider;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\TicketPluginServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

#[WithMigration]
class TestCase extends \Orchestra\Testbench\TestCase
{
    use InteractsWithPest;
    use LazilyRefreshDatabase;

    public static array $registerPanels = [];

    protected function setUp(): void
    {
        parent::setUp();

        Testable::mixin(new LivewireAssertionMixin);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            LaravelQueueDebounceServiceProvider::class,

            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,

            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,

            TicketPluginServiceProvider::class,
            CopilotServiceProvider::class,
            PowerJoinsServiceProvider::class,

            LivewireServiceProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        config()->set('padmission-tickets.models.'.Authenticatable::class, User::class);

        Gate::policy(Ticket::class, TestTicketPolicy::class);

        $plugin = TicketPlugin::make()
            ->allSupportersQuery(fn () => User::query())
            ->registerResources();

        $panel = Panel::make()
            ->default()
            ->id('test')
            ->path('test')
            ->plugin($plugin);

        Filament::registerPanel($panel);
        Filament::setCurrentPanel($panel);

        Filament::registerPanel(Panel::make()->id('test2')->path('test2')->plugin(clone $plugin));
        Filament::registerPanel(Panel::make()->id('test3')->path('test3')->plugin(clone $plugin));
    }

    // Helper methods
    public function login(?Authenticatable $user = null): Authenticatable
    {
        $user ??= User::factory()->create();

        $this->actingAs($user);

        return $user;
    }

    public function resetPanels(): void
    {
        static::$registerPanels = [];
    }

    public function registerPanels(array $panels): void
    {
        foreach ($panels as $panel) {
            $this->registerPanel($panel);
        }
    }

    public function registerPanel(Panel $panel): void
    {
        static::$registerPanels[$panel->getId()] = $panel;
        // app(PanelRegistry::class)->register($panel);
    }

    public function modifyPlugin(Closure $callback): void
    {
        $plugin = TicketPlugin::get();

        $callback($plugin);

        app()->instance('filament.plugins.padmission-tickets', $plugin);
    }
}
