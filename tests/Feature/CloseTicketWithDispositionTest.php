<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;

it('can close a ticket with a disposition', function () {
    // Create a ticket
    $ticket = Ticket::factory()->create(['status_id' => 1]);

    // Create a disposition
    $disposition = TicketDisposition::factory()->create();

    // Close the ticket with the disposition
    $ticket->close($disposition);
    $ticket->refresh();

    expect($ticket->isClosed)->toBeTrue();
    expect($ticket->disposition_id)->toBe($disposition->getKey());
    expect($ticket->disposition->is($disposition))->toBeTrue();
});
