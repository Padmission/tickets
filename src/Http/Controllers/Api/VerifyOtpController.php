<?php

namespace Padmission\Tickets\Http\Controllers\Api;

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
        $oneTimePassword = $request->get('otp');

        $expectedOtp = $request->session()->get('padmission-tickets::otp.code');
        $expiresAt = $request->session()->get('padmission-tickets::otp.expires_at');

        abort_if(now()->isAfter($expiresAt), Response::HTTP_GONE, 'OTP expired');
        abort_if($oneTimePassword !== $expectedOtp, Response::HTTP_UNAUTHORIZED, 'Invalid OTP');

        $userKey = $request->session()->get('padmission-tickets::otp.user_key');

        $request->session()->forget('padmission-tickets::otp.code');
        $request->session()->forget('padmission-tickets::otp.user_key');
        $request->session()->forget('padmission-tickets::otp.expires_at');

        $request->session()->put('padmission-tickets::user_key', $userKey);

        return response()->json();
    }
}
