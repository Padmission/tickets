<?php

namespace Padmission\Tickets\Support;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Http\Controllers\Api\RequestOtpController;
use Padmission\Tickets\Http\Controllers\Api\VerifyOtpController;
use Padmission\Tickets\TicketPlugin;

/*
 * Signing in to the chat with an emailed code skips the password, its second
 * factor and the panel's own login, so only a panel that turns it on offers
 * it, and it is never offered to an account that has more to lose than a
 * conversation: one with multi-factor authentication, a supporter on any
 * ticket panel, or anyone the panel itself would turn away.
 */
class EmailAuthentication
{
    public static function routes(): void
    {
        Route::middleware(['web'])
            ->prefix('padmission-tickets/api')
            ->as('padmission-tickets::.')
            ->group(function () {
                Route::post('/otp-request', RequestOtpController::class)->name('otp.request');
                Route::post('/otp-verify', VerifyOtpController::class)->name('otp.verify');
            });
    }

    public static function isEnabled(): bool
    {
        return static::configs() !== [];
    }

    public static function admits(Model $user): bool
    {
        if (static::hasMultiFactorAuthentication($user) || static::supportsTickets($user)) {
            return false;
        }

        foreach (static::configs() as $panelId => $config) {
            if (! $config->getAllowGuests() && $user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getPanel($panelId))) {
                continue;
            }

            if ($config->allowsEmailAuthenticationFor($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, ChatWidgetConfig>
     */
    protected static function configs(): array
    {
        $configs = [];

        foreach (array_keys(Filament::getPanels()) as $panelId) {
            $config = TicketPlugin::find($panelId)?->getChatWidgetConfig();

            if ($config?->getAllowEmailAuthentication()) {
                $configs[$panelId] = $config;
            }
        }

        return $configs;
    }

    protected static function hasMultiFactorAuthentication(Model $user): bool
    {
        return ($user instanceof HasAppAuthentication && filled($user->getAppAuthenticationSecret()))
            || ($user instanceof HasEmailAuthentication && $user->hasEmailAuthentication());
    }

    protected static function supportsTickets(Model $user): bool
    {
        foreach (array_keys(Filament::getPanels()) as $panelId) {
            if (TicketPlugin::find($panelId)?->isSupporter($user)) {
                return true;
            }
        }

        return false;
    }
}
