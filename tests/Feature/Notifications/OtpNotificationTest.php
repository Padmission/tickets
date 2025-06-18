<?php

use Padmission\Tickets\Notifications\OtpNotification;

test('OtpNotification contains the OTP', function () {
    $otp = '123456';
    $notification = new OtpNotification($otp);

    expect($notification->otp)->toBe($otp);

    $html = strip_tags($notification->toMail(null)->render());

    expect(str_contains($html, $otp))->toBeTrue();
});
