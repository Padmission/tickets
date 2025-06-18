<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Notifications\EmailVerificationNotification;
use Padmission\Tickets\TicketPlugin;
use Symfony\Component\HttpFoundation\Response;

class RequestOtpController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $config = TicketPlugin::get()->getChatWidgetConfig();

        $email = $request->get('email');
        $user = $this->findUser($email);

        abort_unless($user, Response::HTTP_NOT_FOUND);

        $otp = str(random_int(0, 999999))
            ->padLeft(6, '0')
            ->toString();

        session()->put('padmission-tickets::otp.code', $otp);
        session()->put('padmission-tickets::otp.user_key', $user->getKey());
        session()->put(
            'padmission-tickets::otp.expires_at',
            now()->addMinutes($config->getOtpExpiresAfterMinutes())
        );

        Notification::send(
            (new AnonymousNotifiable)->route('mail', $email),
            resolve(EmailVerificationNotification::class, ['otp' => $otp])
        );

        return response()->json();
    }

    protected function findUser(string $email): ?Authenticatable
    {
        $userClass = TicketPlugin::resolveModelClass(Authenticatable::class);

        return $userClass::query()
            ->where('email', $email)
            ->firstOrFail();
    }
}
