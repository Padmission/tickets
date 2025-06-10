<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;
use Padmission\Tickets\TicketPlugin;

it('can close a ticket with a disposition', function () {
    // Ensure we're using the test panel
    $panel = Filament::getCurrentPanel();

    // Create a closed status first - needed for the close method to work
    $closedStatus = TicketStatus::factory()->create([
        'display_name' => 'Closed',
        'order' => 999, // High number ensures it's the last one when ordering DESC
        'panel' => $panel->getId(), // Match the panel ID set in TestCase
    ]);

    // Create test data - don't set panel on ticket as it doesn't have that column
    $ticket = Ticket::factory()->create();
    $disposition = TicketDisposition::factory()->create([
        'panel' => $panel->getId()
    ]);

    $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);
    $closedStatus = $statusModel::query()->orderBy('order', 'DESC')->first();

    // Close the ticket with a disposition
    $ticket->close($disposition);

    // Verify the ticket was closed with the correct disposition
    $ticket->refresh();
    expect($ticket->isClosed)->toBeTrue();
    expect($ticket->disposition_id)->toBe($disposition->getKey());
    expect($ticket->disposition->is($disposition))->toBeTrue();
    expect($ticket->status_id)->toBe($closedStatus->getKey());
});
