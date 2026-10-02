<?php

namespace Padmission\Tickets\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

/*
 * Laravel's session check, for the chat's JSON API: an ended session gets a
 * 401, never a redirect to a login page the host may not even name "login".
 */
class AuthenticateChatSession extends AuthenticateSession
{
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
