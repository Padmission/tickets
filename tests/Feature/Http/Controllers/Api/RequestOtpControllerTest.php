<?php

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Notifications\OtpNotification;
use Padmission\Tickets\Tests\Fixtures\Users\DeactivatedUser;
use Padmission\Tickets\Tests\Fixtures\Users\MultiFactorUser;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;
use Pest\Expectation;

beforeEach(function () {
    // Nobody supports tickets here, so every user is someone the chat may let in by code.
    foreach (['test', 'test2', 'test3'] as $panelId) {
        TicketPlugin::get($panelId)->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));
    }

    Notification::fake();
});

function requestCode(string $email, string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/padmission-tickets/api/otp-request', ['email' => $email]);
}

describe('Without email authentication', function () {
    it('registers no code sign-in routes', function () {
        expect(Route::has('padmission-tickets::.otp.request'))->toBeFalse()
            ->and(Route::has('padmission-tickets::.otp.verify'))->toBeFalse();

        $this->postJson('/padmission-tickets/api/otp-request', ['email' => 'test@example.com'])->assertNotFound();
        $this->postJson('/padmission-tickets/api/otp-verify', ['otp' => '123456'])->assertNotFound();
    });

    it('refuses the routes when the panel turns it off after they were registered', function () {
        $this->enableEmailAuthentication(ChatWidgetConfig::make()->allowEmailAuthentication(fn (): bool => false));
        User::factory()->create(['email' => 'test@example.com']);

        requestCode('test@example.com')->assertNotFound();

        Notification::assertNothingSent();
    });
});

describe('With email authentication', function () {
    beforeEach(function () {
        $this->enableEmailAuthentication();
    });

    it('stores the code and the user in the session', function () {
        $this->freezeTime();

        $user = User::factory()->create(['email' => 'test@example.com']);

        requestCode('test@example.com')->assertOk();

        expect(session()->get('padmission-tickets::otp'))
            ->user_key->toBe($user->getKey())
            ->code->scoped(
                fn (Expectation $otp) => $otp
                    ->toBeString()
                    ->toBeNumeric()
                    ->toHaveLength(6)
            )
            ->expires_at->toEqual(Carbon::now()->addMinutes(10));

        Notification::assertSentTo($user, OtpNotification::class);
    });

    it('answers an unknown email exactly as a known one, without a code', function () {
        requestCode('nobody@example.com')->assertOk()->assertExactJson([]);

        expect(session()->get('padmission-tickets::otp'))->toBeNull();

        Notification::assertNothingSent();
    });

    it('sends no code to an account that must sign in with its password and second factor, saying nothing of why', function (Closure $makeUser) {
        $user = $makeUser();

        requestCode($user->email)->assertOk()->assertExactJson([]);

        expect(session()->get('padmission-tickets::otp'))->toBeNull();

        Notification::assertNothingSent();
    })->with([
        'with multi-factor authentication' => function (): Authenticatable {
            config()->set('padmission-tickets.models.'.Authenticatable::class, MultiFactorUser::class);

            return MultiFactorUser::query()->create(['name' => 'Mfa', 'email' => 'mfa@example.com', 'password' => 'x']);
        },
        'a supporter' => function (): Authenticatable {
            $user = User::factory()->create(['email' => 'staff@example.com']);

            TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($user->id));

            return $user;
        },
        'deactivated, so the panel refuses them' => function (): Authenticatable {
            config()->set('padmission-tickets.models.'.Authenticatable::class, DeactivatedUser::class);

            return DeactivatedUser::query()->create(['name' => 'Gone', 'email' => 'gone@example.com', 'password' => 'x']);
        },
        'refused by the host' => function (): Authenticatable {
            TicketPlugin::get()->showChatWidget(config: ChatWidgetConfig::make()
                ->allowEmailAuthentication()
                ->allowEmailAuthenticationFor(fn (User $user): bool => $user->name !== 'Global Admin'));

            return User::factory()->create(['name' => 'Global Admin', 'email' => 'admin@example.com']);
        },
    ]);

    it('lets in a guest the panel refuses when guests are allowed', function () {
        TicketPlugin::get()->showChatWidget(config: ChatWidgetConfig::make()->allowEmailAuthentication(allowGuests: true));
        config()->set('padmission-tickets.models.'.Authenticatable::class, DeactivatedUser::class);
        $guest = DeactivatedUser::query()->create(['name' => 'Guest', 'email' => 'guest@example.com', 'password' => 'x']);

        requestCode('guest@example.com')->assertOk();

        Notification::assertSentTo($guest, OtpNotification::class);
    });

    it('limits codes per email, known or not, so the limit says nothing of who has an account', function () {
        User::factory()->create(['email' => 'test@example.com']);

        requestCode('test@example.com')->assertOk();
        requestCode('test@example.com', '10.0.0.2')->assertTooManyRequests()->assertJsonStructure(['error']);

        requestCode('nobody@example.com')->assertOk();
        requestCode('nobody@example.com', '10.0.0.2')->assertTooManyRequests()->assertJsonStructure(['error']);
    });

    it('does not let one client block codes for everyone else', function () {
        User::factory()->create(['email' => 'first@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);

        foreach (range(1, 10) as $attempt) {
            requestCode("spam{$attempt}@example.com", '10.0.0.9');
        }

        requestCode('other@example.com', '10.0.0.3')->assertOk();

        Notification::assertSentTo($other, OtpNotification::class);
    });

    it('limits codes per address too', function () {
        foreach (range(1, 5) as $attempt) {
            requestCode("person{$attempt}@example.com", '10.0.0.9')->assertOk();
        }

        requestCode('person6@example.com', '10.0.0.9')->assertTooManyRequests();
    });

    it('keeps the code already sent when a request is limited', function () {
        User::factory()->create(['email' => 'test@example.com']);

        requestCode('test@example.com')->assertOk();
        $sent = session('padmission-tickets::otp.code');

        requestCode('test@example.com')->assertTooManyRequests();

        expect(session('padmission-tickets::otp.code'))->toBe($sent);
    });
});
