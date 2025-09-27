<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Login;
use App\Models\User;
use App\Policies\TicketPolicy;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class DefaultPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        Gate::policy(Ticket::class, TicketPolicy::class);

        return $panel
            ->default()
            ->id('default')
            ->path('/')
            ->login(Login::class)
            ->colors([
                'primary' => [
                    50 => '238, 246, 251',
                    100 => '218, 234, 246',
                    200 => '181, 214, 238',
                    300 => '143, 193, 229',
                    400 => '106, 173, 220',
                    500 => '70, 153, 212',
                    600 => '42, 124, 182',
                    700 => '32, 93, 137',
                    800 => '21, 62, 91',
                    900 => '11, 31, 46',
                    950 => '6, 17, 25',
                ],
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(
                TicketPlugin::make()
                    ->registerResources()
                    ->allSupportersQuery(User::query())
                    ->allowLinkedTickets(only: ['second'])
                    ->showChatWidget(
                        config: ChatWidgetConfig::make()
                            ->allowFileUploads(maxFileSize: 20 * 1024 * 1024)
                            ->allowScreenshots()
                    )
            );
    }
}
