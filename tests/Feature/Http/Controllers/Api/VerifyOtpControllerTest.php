<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Testing\TestResponse;
use Padmission\Tickets\Tests\Fixtures\Users\MultiFactorUser;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    foreach (['test', 'test2', 'test3'] as $panelId) {
        TicketPlugin::get($panelId)->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));
    }

    $this->enableEmailAuthentication();

    $this->user = User::factory()->create();
});

function sentCode(Authenticatable $user, string $code = '123456'): void
{
    session()->put('padmission-tickets::otp.code', $code);
    session()->put('padmission-tickets::otp.expires_at', now()->addMinutes(5));
    session()->put('padmission-tickets::otp.user_key', $user->getAuthIdentifier());
}

function verifyCode(string $code, string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/padmission-tickets/api/otp-verify', ['otp' => $code]);
}

it('signs the user in and gives them a new session', function () {
    sentCode($this->user);
    $before = session()->getId();

    verifyCode('123456')->assertOk();

    expect(session('padmission-tickets::user_key'))->toBe($this->user->getKey())
        ->and(session()->getId())->not->toBe($before)
        ->and(session('padmission-tickets::otp.code'))->toBeNull();
});

it('refuses an expired code', function () {
    sentCode($this->user);
    session()->put('padmission-tickets::otp.expires_at', now()->subMinute());

    verifyCode('123456')->assertStatus(410)->assertJsonStructure(['error']);

    expect(session('padmission-tickets::user_key'))->toBeNull();
});

it('refuses when no code was sent', function () {
    verifyCode('123456')->assertStatus(410);

    expect(session('padmission-tickets::user_key'))->toBeNull();
});

it('refuses a wrong code', function () {
    sentCode($this->user);

    verifyCode('654321')->assertUnauthorized()->assertJsonStructure(['error']);

    expect(session('padmission-tickets::user_key'))->toBeNull();
});

it('throws the code away after five wrong guesses, so the right one no longer works', function () {
    sentCode($this->user);

    foreach (range(1, 5) as $guess) {
        verifyCode('00000'.$guess)->assertUnauthorized();
    }

    verifyCode('123456', '10.0.0.2')->assertStatus(410);

    expect(session('padmission-tickets::user_key'))->toBeNull()
        ->and(session('padmission-tickets::otp.code'))->toBeNull();
});

it('limits guesses per address', function () {
    foreach (range(1, 10) as $guess) {
        sentCode($this->user);
        verifyCode('99999'.($guess % 10), '10.0.0.9');
    }

    sentCode($this->user);

    verifyCode('123456', '10.0.0.9')->assertTooManyRequests()->assertJsonStructure(['error']);

    expect(session('padmission-tickets::user_key'))->toBeNull();
});

it('does not let one client block sign-in for everyone else', function () {
    foreach (range(1, 10) as $guess) {
        sentCode($this->user);
        verifyCode('99999'.($guess % 10), '10.0.0.9');
    }

    sentCode($this->user);

    verifyCode('123456', '10.0.0.3')->assertOk();
});

it('refuses a code for an account that has since set up multi-factor authentication', function () {
    config()->set('padmission-tickets.models.'.Authenticatable::class, MultiFactorUser::class);
    sentCode(MultiFactorUser::query()->findOrFail($this->user->id));

    verifyCode('123456')->assertUnauthorized();

    expect(session('padmission-tickets::user_key'))->toBeNull();
});
