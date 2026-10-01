<?php

namespace Padmission\Tickets\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Padmission\Tickets\Http\ReadOnlySessionHandler;

/*
 * The chat polls its read endpoints while a page request claims a flashed
 * notification. Saving the session at the end of a read would finish last
 * and write that notification back, so the toast showed again on the next
 * page, and a read has nothing of its own to save. The session is still
 * read, so the person is known.
 *
 * Runs inside the web group, after StartSession, so it cannot replace it;
 * it makes the started session's save write nothing instead.
 */
class ReadOnlySession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $session = $request->hasSession() ? $request->session() : null;

        if ($session instanceof Store && ! $session->getHandler() instanceof ReadOnlySessionHandler) {
            $session->setHandler(new ReadOnlySessionHandler($session->getHandler()));
        }

        return $next($request);
    }

    // The session store outlives the request under Octane and in tests, so the next request writes again.
    public function terminate(Request $request): void
    {
        $session = $request->hasSession() ? $request->session() : null;

        if ($session instanceof Store && ($handler = $session->getHandler()) instanceof ReadOnlySessionHandler) {
            $session->setHandler($handler->inner);
        }
    }
}
