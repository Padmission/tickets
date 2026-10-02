<?php

namespace Padmission\Tickets\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateGuests
{
    public function handle(Request $request, Closure $next)
    {
        $userId = $request->session()->get('padmission-tickets::user_key');

        // A code only signs in someone who is not signed in already, never over a real login.
        if (! $userId || auth()->check()) {
            return $next($request);
        }

        auth()->onceUsingId($userId);

        return $next($request);
    }
}
