<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

it('resolves model classes', function () {
    expect(TicketPlugin::resolveModelClass(Ticket::class))->toBe(Ticket::class);
});

it('returns replaced classes', function () {
    config()->set('padmission-tickets.models.'.Ticket::class, 'NewModelClass');

    expect(TicketPlugin::resolveModelClass(Ticket::class))->toBe('NewModelClass');
});
