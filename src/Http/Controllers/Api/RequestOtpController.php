<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Notifications\OtpNotification;
use Padmission\Tickets\TicketPlugin;

class RequestOtpController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $config = TicketPlugin::get()->getChatWidgetConfig();

        $email = $request->get('email');
        $user = $this->findUser($email);

        if (! $user) {
            return response()->json();
        }

        $otp = str((string) random_int(0, 999999))
            ->padLeft(6, '0')
            ->toString();

        session()->put('padmission-tickets::otp.code', $otp);
        session()->put('padmission-tickets::otp.user_key', $user->getKey());
        session()->put(
            'padmission-tickets::otp.expires_at',
            now()->addMinutes($config->getOtpExpiresAfterMinutes())
        );

        $limiter = resolve(RateLimiter::class);
        $rateLimitKey = 'padmission-tickets::send-otp';

        if ($limiter->tooManyAttempts($rateLimitKey, 1)) {
            return response()->json([
                'error' => __('padmission-tickets::chat.otp_request.errors.rate_limited', ['seconds' => $limiter->availableIn($rateLimitKey)]),
            ], 429);
        }

        $limiter->hit($rateLimitKey, 60);

        Notification::send(
            $user,
            resolve(OtpNotification::class, ['user' => $user, 'otp' => $otp])
        );

        return response()->json();
    }

    protected function findUser(string $email): ?Model
    {
        $userClass = TicketPlugin::resolveModelClass(Authenticatable::class);

        return $userClass::query()
            ->where('email', $email)
            ->first();
    }
}
