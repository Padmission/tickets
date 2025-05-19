<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\Database\Seeders\StatusSeeder;
use Padmission\Tickets\Models\Status;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\NotificationStrategies\NotificationStrategy;
use Padmission\Tickets\Tests\User;
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

test('open/close scopes', function () {
    $ticket = Ticket::factory()->create([
        'closed_at' => null,
    ]);

    expect(Ticket::query()->open()->count())->toEqual(1);
    expect(Ticket::query()->closed()->count())->toEqual(0);

    $ticket->update(['closed_at' => now()]);

    expect(Ticket::query()->open()->count())->toEqual(0);
    expect(Ticket::query()->closed()->count())->toEqual(1);
});

it('can be closed', function () {
    (new StatusSeeder)->run(panel: 'test');

    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->freezeSecond();

    $ticket->close(closedBy: $user->id);

    expect($ticket->refresh())
        ->isClosed->toBeTrue()
        ->status->toEqual(Status::getClosedStatus())
        ->closed_at->toEqual(now())
        ->closed_by->toEqual($user->id);
});

it('cannot be closed twice', function () {
    (new StatusSeeder)->run(panel: 'test');

    $ticket = Ticket::factory()->closed()->create();

    $this->freezeSecond();

    $ticket->close(closedBy: 99);

    expect($ticket->refresh())->closed_by->not->toEqual(99);
});
