<?php

namespace Padmission\Tickets\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Carbon\Carbon;
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
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\InteractsWithPest;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketNotification;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;
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
            
            // Add the Queue Debouncer Service Provider
            \Mpbarlow\LaravelQueueDebouncer\ServiceProvider::class,

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
        config()->set('padmission-tickets.models.'.Authenticatable::class, \Padmission\Tickets\Tests\User::class);

        Gate::policy(Ticket::class, TestTicketPolicy::class);

        $panel = Panel::make()
            ->default()
            ->id('test')
            ->path('test')
            ->plugin(
                TicketPlugin::make()->registerResources()
            );

        Filament::registerPanel(fn () => $panel);

        Filament::setCurrentPanel($panel);

        $app->bind(\Mpbarlow\LaravelQueueDebouncer\Contracts\CacheKeyProvider::class, function () {
            return new class implements \Mpbarlow\LaravelQueueDebouncer\Contracts\CacheKeyProvider {
                public function getKey($job): string {
                    return 'test_key_' . md5(serialize($job));
                }
            };
        });

        $app->bind(\Mpbarlow\LaravelQueueDebouncer\Contracts\UniqueIdentifierProvider::class, function () {
            return new class implements \Mpbarlow\LaravelQueueDebouncer\Contracts\UniqueIdentifierProvider {
                public function getIdentifier(): string {
                    return 'test_identifier_' . uniqid();
                }
            };
        });
    }

    // Helper methods
    public function login(?Authenticatable $user = null): Authenticatable
    {
        $user ??= User::factory()->create();

        $this->actingAs($user);

        return $user;
    }

    // In TestCase.php
    public function createNotificationHistory(Ticket $ticket, User $user, Carbon $timestamp): TicketNotification
    {
        return $ticket->ticketNotifications()->create([
            'user_id' => $user->getKey(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function createTicketActivity(Ticket $ticket, ActivityType $type, Carbon $timestamp): TicketActivity
    {
        return $ticket->ticketActivities()->create([
            'type' => $type,
            'sender' => ActivitySender::User,
            'user_id' => 1,
            'content' => 'Test activity',
            'created_at' => $timestamp,
        ]);
    }
}
