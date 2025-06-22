<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketNotification;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class CustomTicket extends Ticket {}

class CustomTicketActivity extends TicketActivity {}

class CustomTicketDisposition extends TicketDisposition {}

class CustomTicketStatus extends TicketStatus {}

class CustomTicketPriority extends TicketPriority {}

class CustomTicketNotification extends TicketNotification {}

it('resolves model classes', function () {
    expect(TicketPlugin::resolveModelClass(Ticket::class))->toBe(Ticket::class);
});

it('returns replaced classes', function () {
    config()->set('padmission-tickets.models.'.Ticket::class, 'NewModelClass');

    expect(TicketPlugin::resolveModelClass(Ticket::class))->toBe('NewModelClass');
});

it('ensures model resolution works with custom models', function (string $given, string $resolved) {
    config()->set('padmission-tickets.models', [$given => $resolved]);

    expect(TicketPlugin::resolveModelClass($given))->toBe($resolved);
})->with([
    [Ticket::class, CustomTicket::class],
    [TicketActivity::class, CustomTicketActivity::class],
    [TicketDisposition::class, CustomTicketDisposition::class],
    [TicketStatus::class, CustomTicketStatus::class],
    [TicketPriority::class, CustomTicketPriority::class],
    [TicketActivity::class, CustomTicketActivity::class],
    [TicketNotification::class, CustomTicketNotification::class],
]);
