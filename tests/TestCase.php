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
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\LivewireServiceProvider;
use Mpbarlow\LaravelQueueDebouncer\Contracts\CacheKeyProvider;
use Mpbarlow\LaravelQueueDebouncer\Contracts\UniqueIdentifierProvider;
use Mpbarlow\LaravelQueueDebouncer\ServiceProvider as LaravelQueueDebounceServiceProvider;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\InteractsWithPest;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
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

            LaravelQueueDebounceServiceProvider::class,

            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
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
        config()->set('padmission-tickets.models.'.Authenticatable::class, User::class);

        Gate::policy(Ticket::class, TestTicketPolicy::class);

        $panel = Panel::make()
            ->default()
            ->id('test')
            ->path('test')
            ->colors([ // set default primary color for the panel, needed in CloseTicketActionTest.php
                'primary' => '#4F46E5',
            ])
            ->plugin(
                TicketPlugin::make()
                    ->allSupportersQuery(fn () => User::query())
                    ->registerResources()
            );

        Filament::registerPanel(fn () => $panel);

        Filament::setCurrentPanel($panel);

        $app->bind(CacheKeyProvider::class, fn () => new class implements CacheKeyProvider
        {
            public function getKey($job): string
            {
                return 'test_key_'.md5(serialize($job));
            }
        });

        $app->bind(UniqueIdentifierProvider::class, fn () => new class implements UniqueIdentifierProvider
        {
            public function getIdentifier(): string
            {
                return 'test_identifier_'.uniqid();
            }
        });
    }

    // Helper methods
    public function login(?Authenticatable $user = null): Authenticatable
    {
        $user ??= User::factory()->create();

        $this->actingAs($user);

        return $user;
    }

    public function modifyPlugin(Closure $callback): void
    {
        $plugin = TicketPlugin::get();

        $callback($plugin);

        app()->instance('filament.plugins.padmission-tickets', $plugin);
    }
}
