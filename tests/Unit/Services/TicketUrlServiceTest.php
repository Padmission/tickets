<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketUrlService;

beforeEach(function () {
    $this->service = new TicketUrlService;
});

test('can generate action URL with valid ticket URL', function () {
    $ticket = Ticket::factory()->create([
        'data' => [
            'url' => 'https://example.com/tickets',
        ],
    ]);

    $actionUrl = $this->service->getActionUrl($ticket);

    expect($actionUrl)->toBe("https://example.com/tickets#ticket-{$ticket->id}");
});

test('uses app URL when no ticket URL provided', function () {
    $ticket = Ticket::factory()->create([
        'data' => [],
    ]);

    // When no URL is provided, it uses url('/') which should be the current app URL
    $expectedUrl = url('/');

    $actionUrl = $this->service->getActionUrl($ticket);

    expect($actionUrl)->toBe("{$expectedUrl}#ticket-{$ticket->id}");
});
