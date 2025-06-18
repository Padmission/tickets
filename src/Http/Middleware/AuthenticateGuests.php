<?php

namespace Padmission\Tickets\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateGuests
{
    public function handle(Request $request, Closure $next)
    {
        $userId = $request->session()->get('padmission-tickets::user_key');

        if (! $userId) {
            return $next($request);
        }

        auth()->onceUsingId($userId);

        return $next($request);
    }
}
