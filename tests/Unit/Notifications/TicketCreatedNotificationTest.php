<?php

use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\Tests\User;

it('contains link to view page', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $html = (new TicketCreatedNotification($ticket))->toMail($user)->render();

    $this->assertStringContainsString(
        TicketResource::getUrl('view', ['record' => $ticket]),
        $html
    );
});
