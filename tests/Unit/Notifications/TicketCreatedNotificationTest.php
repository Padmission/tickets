<?php

use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

it('contains link to view page', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $html = (new TicketCreatedNotification($ticket))->toMail($user)->render();

    $this->assertStringContainsString(
        TicketResource::getUrl('view', ['record' => $ticket]),
        $html
    );
});

it('respects the channels defined in the config', function () {
    $this->replacePlugin(
        TicketPlugin::make()->notificationChannels([
            'channel_1',
            'channel_2',
        ])
    );

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $notification = new TicketCreatedNotification($ticket);

    expect($notification->via($user))
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain('channel_1')
        ->toContain('channel_2');
});
