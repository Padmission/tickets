<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\Notifications\OtpNotification;
use Padmission\Tickets\Tests\User;
use Pest\Expectation;

it('stores otp and email in session', function () {
    $this->freezeTime();

    $user = User::factory()->create(['email' => 'test@example.com']);

    $this
        ->post('/padmission-tickets/api/otp-request', [
            'email' => 'test@example.com',
        ])
        ->assertStatus(200);

    expect(session()->get('padmission-tickets::otp'))
        ->user_key->toBe($user->getKey())
        ->code->scoped(
            fn (Expectation $otp) => $otp
                ->toBeString()
                ->toBeNumeric()
                ->toHaveLength(6)
        )
        ->expires_at->toEqual(Carbon::now()->addMinutes(10));
});

it('does not show an error when no user with email was found', function () {
    $this
        ->post('/padmission-tickets/api/otp-request', [
            'email' => 'nonexistent@example.com',
        ])
        ->assertStatus(200);

    expect(session()->get('padmission-tickets::otp'))->toBeNull();
});

it('sends a notification', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->post('/padmission-tickets/api/otp-request', [
        'email' => 'test@example.com',
    ]);

    Notification::assertSentTo(
        $user,
        OtpNotification::class
    );
});

it('throttles notifications to one per minute', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this
        ->post('/padmission-tickets/api/otp-request', [
            'email' => 'test@example.com',
        ])
        ->assertStatus(200);

    $this
        ->post('/padmission-tickets/api/otp-request', [
            'email' => 'test@example.com',
        ])
        ->assertStatus(429)
        ->assertJsonStructure(['error']);
});
