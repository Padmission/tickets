<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padmission\Tickets\Support\EmailAuthentication;
use Padmission\Tickets\TicketPlugin;
use Symfony\Component\HttpFoundation\Response;

class VerifyOtpController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    // Six digits allow a million codes; five guesses at one leave a one-in-200,000 chance.
    protected const MAX_GUESSES = 5;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(EmailAuthentication::isEnabled(), 404);

        $limiter = resolve(RateLimiter::class);
        $rateLimitKey = 'padmission-tickets::otp-verify:ip:'.$request->ip();

        if ($limiter->tooManyAttempts($rateLimitKey, 10)) {
            return response()->json([
                'error' => __('padmission-tickets::chat.errors.too_many_requests', ['seconds' => $limiter->availableIn($rateLimitKey)]),
            ], 429);
        }

        $limiter->hit($rateLimitKey, 60);

        $session = $request->session();
        $sent = $session->get('padmission-tickets::otp');

        if (! is_array($sent) || blank($sent['code'] ?? null) || now()->isAfter($sent['expires_at'] ?? now()->subSecond())) {
            $session->forget('padmission-tickets::otp');

            return response()->json([
                'error' => __('padmission-tickets::chat.otp_verify.errors.expired'),
            ], Response::HTTP_GONE);
        }

        $user = $this->findUser($sent['user_key'] ?? null);

        if (! hash_equals((string) $sent['code'], (string) $request->input('otp')) || $user === null || ! EmailAuthentication::admits($user)) {
            $attempts = ($sent['attempts'] ?? 0) + 1;

            $attempts >= static::MAX_GUESSES || $user === null || ! EmailAuthentication::admits($user)
                ? $session->forget('padmission-tickets::otp')
                : $session->put('padmission-tickets::otp.attempts', $attempts);

            return response()->json([
                'error' => __('padmission-tickets::chat.otp_verify.errors.invalid_otp'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $session->forget('padmission-tickets::otp');
        $session->regenerate();
        $session->put('padmission-tickets::user_key', $user->getKey());

        return response()->json([
            'user_key' => $user->getKey(),
        ]);
    }

    protected function findUser(mixed $key): ?Model
    {
        if (blank($key)) {
            return null;
        }

        return TicketPlugin::resolveUserModelClass()::query()->find($key);
    }
}
