<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;

it('can close a ticket with a disposition', function () {

    // Create a ticket
    $ticket = Ticket::factory()->create(['status_id' => 1]);

    $disposition = TicketDisposition::factory()->create();
    $status = TicketStatus::factory()->create();

    // Close the ticket with the disposition
    $ticket->close($disposition);
    $ticket->refresh();

    expect($ticket->isClosed)->toBeTrue();
    expect($ticket->disposition_id)->toBe($disposition->getKey());
    expect($ticket->disposition->is($disposition))->toBeTrue();
});
