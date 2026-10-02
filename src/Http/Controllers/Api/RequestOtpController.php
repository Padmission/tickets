<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Notifications\OtpNotification;
use Padmission\Tickets\Support\EmailAuthentication;
use Padmission\Tickets\TicketPlugin;

class RequestOtpController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(EmailAuthentication::isEnabled(), 404);

        $email = trim((string) $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ])['email']);

        // Limited by the address asked for and by the client asking, before anyone is looked up,
        // so a limit reads the same whether or not the address has an account.
        $limiter = resolve(RateLimiter::class);
        $keys = [
            'padmission-tickets::otp-request:email:'.hash('sha256', mb_strtolower($email)) => 1,
            'padmission-tickets::otp-request:ip:'.$request->ip() => 5,
        ];

        foreach ($keys as $key => $maxAttempts) {
            if ($limiter->tooManyAttempts($key, $maxAttempts)) {
                return response()->json([
                    'error' => __('padmission-tickets::chat.otp_request.errors.rate_limited', ['seconds' => $limiter->availableIn($key)]),
                ], 429);
            }
        }

        foreach (array_keys($keys) as $key) {
            $limiter->hit($key, 60);
        }

        $user = $this->findUser($email);

        // Answered alike for an unknown address and an account that must sign in properly.
        if ($user === null || ! EmailAuthentication::admits($user)) {
            return response()->json();
        }

        $otp = str((string) random_int(0, 999999))
            ->padLeft(6, '0')
            ->toString();

        $config = TicketPlugin::get()->getChatWidgetConfig();

        session()->put('padmission-tickets::otp', [
            'code' => $otp,
            'user_key' => $user->getKey(),
            'expires_at' => now()->addMinutes($config->getOtpExpiresAfterMinutes()),
            'attempts' => 0,
        ]);

        Notification::send(
            $user,
            resolve(OtpNotification::class, ['user' => $user, 'otp' => $otp])
        );

        return response()->json();
    }

    protected function findUser(string $email): ?Model
    {
        $userClass = TicketPlugin::resolveUserModelClass();

        return $userClass::query()
            ->where('email', $email)
            ->first();
    }
}
