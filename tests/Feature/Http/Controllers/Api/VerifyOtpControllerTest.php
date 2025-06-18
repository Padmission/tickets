<?php

use Illuminate\Support\Facades\RateLimiter;
use Padmission\Tickets\Tests\User;

test('it verifies OTP successfully', function () {
    $user = User::factory()->create();

    session()->put('padmission-tickets::otp.code', '123456');
    session()->put('padmission-tickets::otp.expires_at', now()->addMinutes(5));
    session()->put('padmission-tickets::otp.user_key', $user->getKey());

    $this
        ->post('/padmission-tickets/api/otp-verify', ['otp' => '123456'])
        ->assertStatus(200);

    expect(session('padmission-tickets::user_key'))->toBe($user->getKey());
});

test('it denies OTP verification after exceeding rate limit', function () {
    RateLimiter::hit('padmission-tickets::verify-otp', 1);
    RateLimiter::hit('padmission-tickets::verify-otp', 1);
    RateLimiter::hit('padmission-tickets::verify-otp', 1);
    RateLimiter::hit('padmission-tickets::verify-otp', 1);
    RateLimiter::hit('padmission-tickets::verify-otp', 1);

    $this
        ->post('/padmission-tickets/api/otp-verify', ['otp' => '123456'])
        ->assertStatus(429)
        ->assertJsonStructure(['error']);

    expect(session('padmission-tickets::user_key'))->toBeNull();
});

test('it denies OTP verification when OTP is expired', function () {
    $user = User::factory()->create();

    session()->put('padmission-tickets::otp.code', '123456');
    session()->put('padmission-tickets::otp.user_key', $user->getKey());
    session()->put('padmission-tickets::otp.expires_at', now()->subMinutes(1));

    $this
        ->post('/padmission-tickets/api/otp-verify', ['otp' => '123456'])
        ->assertStatus(410)
        ->assertJsonStructure(['error']);

    expect(session('padmission-tickets::user_key'))->toBeNull();
});

test('it denies OTP verification when OTP is incorrect', function () {
    $user = User::factory()->create();

    session()->put('padmission-tickets::otp.code', '123456');
    session()->put('padmission-tickets::otp.user_key', $user->getKey());
    session()->put('padmission-tickets::otp.expires_at', now()->addMinutes(5));

    $this
        ->post('/padmission-tickets/api/otp-verify', ['otp' => '654321'])
        ->assertStatus(401)
        ->assertJsonStructure(['error']);

    expect(session('padmission-tickets::user_key'))->toBeNull();
});
