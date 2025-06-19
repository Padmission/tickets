<?php

use Padmission\Tickets\Notifications\OtpNotification;
use Padmission\Tickets\Tests\User;

test('OtpNotification contains the OTP', function () {
    $otp = '123456';
    $user = User::factory()->create();
    $notification = new OtpNotification($user, $otp);

    expect($notification->otp)->toBe($otp);

    $html = strip_tags($notification->toMail(null)->render());

    expect(str_contains($html, $otp))->toBeTrue();
});
