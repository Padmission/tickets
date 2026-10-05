<?php

namespace Padmission\Tickets\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * The ticket rules ask Filament which panel they are in and who is signed in
 * to it, so a token request outside any panel would get the default panel and
 * nobody. This puts the request inside the staff panel as the token's user,
 * so the panel's ticket query, scopes, policies and notifications apply
 * exactly as on its pages, and refuses anyone who may not enter that panel.
 */
class PrepareStaffApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $panel = Filament::getPanel((string) config('padmission-tickets.staff_api.panel'));

        abort_unless($user instanceof FilamentUser && $user->canAccessPanel($panel), 403);

        Filament::setCurrentPanel($panel);

        // setUser holds the user for this request only; nothing is written to a session.
        Filament::auth()->setUser($user);

        return $next($request);
    }
}
