<?php

use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Http\Middleware\AuthenticateGuests;
use Padmission\Tickets\Notifications\OtpNotification;

it('does not expose the legacy chat widget classes or routes', function () {
    expect(class_exists(ChatWidgetConfig::class))->toBeFalse()
        ->and(class_exists(AuthenticateGuests::class))->toBeFalse()
        ->and(class_exists(OtpNotification::class))->toBeFalse();
});
