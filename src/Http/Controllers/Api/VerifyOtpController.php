<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOtpController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $limiter = resolve(RateLimiter::class);
        $rateLimitKey = 'padmission-tickets::verify-otp';

        if ($limiter->tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'error' => __('padmission-tickets::chat.errors.too_many_requests', ['seconds' => $limiter->availableIn($rateLimitKey)]),
            ], 429);
        }

        $limiter->hit($rateLimitKey, 60);

        $oneTimePassword = $request->get('otp');

        $expectedOtp = $request->session()->get('padmission-tickets::otp.code');
        $expiresAt = $request->session()->get('padmission-tickets::otp.expires_at');

        if (now()->isAfter($expiresAt)) {
            return response()->json([
                'error' => __('padmission-tickets::chat.otp_verify.errors.expired'),
            ], Response::HTTP_GONE);
        }

        if ($oneTimePassword !== $expectedOtp) {
            return response()->json([
                'error' => __('padmission-tickets::chat.otp_verify.errors.invalid_otp'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userKey = $request->session()->get('padmission-tickets::otp.user_key');

        $request->session()->forget('padmission-tickets::otp.code');
        $request->session()->forget('padmission-tickets::otp.user_key');
        $request->session()->forget('padmission-tickets::otp.expires_at');

        $request->session()->put('padmission-tickets::user_key', $userKey);

        return response()->json([
            'user_key' => $userKey,
        ]);
    }
}
