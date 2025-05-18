<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\NotificationStrategies\NotificationStrategy;
use Padmission\Tickets\TicketPlugin;

it('executes assignment strategy while creating', function () {
    Filament::getPanel('test')->plugin(
        TicketPlugin::make()->assignmentStrategy(
            new class implements AssignmentStrategy
            {
                public function assign($ticket): void
                {
                    $ticket->assignee_id = 2;
                }
            }
        )
    );

    $ticket = Ticket::factory()->make([
        'assignee_id' => null,
        'panel' => 'test',
    ]);

    $ticket->save();

    expect($ticket->assignee_id)->toEqual(2);
});

it('executes notification strategy while creating', function () {
    Notification::fake();

    Filament::getPanel('test')->plugin(
        TicketPlugin::make()->notificationStrategy(
            new class implements NotificationStrategy
            {
                public function notify($ticket): void
                {
                    Notification::send(
                        $ticket->assignee,
                        new TicketCreatedNotification($ticket)
                    );
                }
            }
        )
    );

    $ticket = Ticket::factory()->make([
        'assignee_id' => 1,
        'panel' => 'test',
    ]);

    $ticket->save();

    Notification::assertCount(1);
});
