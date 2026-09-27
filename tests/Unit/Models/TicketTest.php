<?php

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\AssignmentStrategies\AssignmentStrategy;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

it('executes assignment strategy while creating', function () {
    Filament::getPanel('test')->plugin(
        TicketPlugin::make()
            ->allSupportersQuery(fn () => User::query())
            ->assignmentStrategy(
                new class implements AssignmentStrategy
                {
                    public function assign(Ticket $ticket): void
                    {
                        $ticket->assignee_id = 2;
                    }
                }
            )
            ->registerResources()
    );

    $ticket = Ticket::factory()->create([
        'assignee_id' => null,
    ]);

    expect($ticket->assignee_id)->toEqual(2);
});

test('open/close scopes', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->open()->create();

    expect(Ticket::query()->open()->count())->toEqual(1);
    expect(Ticket::query()->closed()->count())->toEqual(0);

    $ticket->update(['closed_at' => now()]);

    expect(Ticket::query()->open()->count())->toEqual(0);
    expect(Ticket::query()->closed()->count())->toEqual(1);
});

it('can be closed', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->open()->create();
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->freezeSecond();

    $ticket->close(closedById: $user->id);

    expect($ticket->refresh())
        ->isClosed->toBeTrue()
        ->status->toEqual(TicketStatus::getClosedStatus())
        ->closed_at->toEqual(now())
        ->closed_by->toEqual($user->id);
});

it('fires the closed event with the explicit closer as actor when unauthenticated', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->open()->create();
    $user = User::factory()->create();

    Event::fake([TicketClosedEvent::class]);

    expect(auth()->user())->toBeNull();

    $ticket->close(closedById: $user->id);

    Event::assertDispatched(
        TicketClosedEvent::class,
        fn (TicketClosedEvent $event): bool => $event->actor?->getKey() === $user->getKey()
    );
});

it('fires the closed event with the explicit closer as actor when another user is authenticated', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->open()->create();
    $authenticatedUser = User::factory()->create();
    $closer = User::factory()->create();

    $this->actingAs($authenticatedUser);

    Event::fake([TicketClosedEvent::class]);

    $ticket->close(closedById: $closer->id);

    Event::assertDispatched(
        TicketClosedEvent::class,
        fn (TicketClosedEvent $event): bool => $event->actor?->getKey() === $closer->getKey()
    );
});

it('cannot be closed twice', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->closed()->create();

    $this->freezeSecond();

    $ticket->close(closedById: 99);

    expect($ticket->refresh())->closed_by->not->toEqual(99);
});

it('closes ticket when status is changed to closed', function () {
    $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);
    $disposition = $dispositionModel::factory()->create();

    (new TicketStatusSeeder)->run();
    $closedStatusId = TicketStatus::getClosedStatus()->getKey();

    $ticket = Ticket::factory()->create(['status_id' => 1]);
    $user = User::factory()->create();

    $this->freezeSecond();
    $this->actingAs($user);

    $ticket->close(null, $user->getKey());

    expect($ticket->refresh())
        ->isClosed->toBeTrue()
        ->status->toEqual(TicketStatus::getClosedStatus())
        ->closed_at->toEqual(now())
        ->closed_by->toEqual($user->id);
});

it('logs status change', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->create(['status_id' => 1]);
    $user = User::factory()->create();

    $ticket->close(closedById: $user->id);

    $this->assertDatabaseHas(TicketActivity::class, [
        'type' => ActivityType::StatusChanged,
        'data' => json_encode([
            'from' => 1,
            'to' => TicketStatus::getClosedStatus()->getKey(),
        ]),
    ]);
});

it('logs priority change', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->create(['priority_id' => 1]);

    $ticket->update(['priority_id' => 2]);

    $this->assertDatabaseHas(TicketActivity::class, [
        'type' => ActivityType::PriorityChanged,
        'data' => json_encode([
            'from' => 1,
            'to' => 2,
        ]),
    ]);
});

describe('Closed status of the ticket\'s own panel and tenant', function () {
    it('writes the closed status of the ticket\'s panel when closed from another panel', function () {
        (new TicketStatusSeeder)->run();

        $ticket = Ticket::factory()->open()->create(['panel' => 'test2']);

        $ticket->close(closedById: User::factory()->create()->id);

        $closed = TicketStatus::withoutGlobalScopes()->where('panel', 'test2')->orderByDesc('order')->first();

        expect($ticket->refresh())
            ->isClosed->toBeTrue()
            ->status_id->toBe($closed->id)
            ->and($closed->id)->not->toBe(TicketStatus::getClosedStatus()->id);
    });

    it('writes the closed status of the ticket\'s tenant when closed by someone in another tenant', function () {
        Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
        Schema::table('ticket_statuses', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
        config()->set('padmission-tickets.tenancy.enabled', true);

        $statuses = collect([1, 2])->mapWithKeys(fn (int $tenant): array => [$tenant => [
            'open' => TicketStatus::factory()->create(['panel' => 'test', 'tenant_id' => $tenant, 'order' => 1]),
            'closed' => TicketStatus::factory()->create(['panel' => 'test', 'tenant_id' => $tenant, 'order' => 2]),
        ]]);

        // The viewer's tenant scope, which the ticket panel's relationship modifier lifts.
        TicketStatus::addGlobalScope('viewer-tenant', fn ($query) => $query->where('ticket_statuses.tenant_id', 1));
        TicketPlugin::get()->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('viewer-tenant'));

        try {
            $ticket = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $statuses[2]['open']->id]);

            $ticket->close(closedById: User::factory()->create()->id);

            expect($ticket->refresh())
                ->isClosed->toBeTrue()
                ->status_id->toBe($statuses[2]['closed']->id);
        } finally {
            TicketStatus::clearBootedModels();
        }
    });

    it('closes a ticket set to its own panel\'s closed status and reopens it when set back', function () {
        (new TicketStatusSeeder)->run();

        $statuses = TicketStatus::withoutGlobalScopes()->where('panel', 'test2')->orderBy('order')->get();
        $ticket = Ticket::factory()->create(['panel' => 'test2', 'status_id' => $statuses->first()->id]);

        $this->actingAs(User::factory()->create());

        $ticket->update(['status_id' => $statuses->last()->id]);

        expect($ticket->refresh()->isClosed)->toBeTrue();

        $ticket->update(['status_id' => $statuses->first()->id]);

        expect($ticket->refresh())
            ->isClosed->toBeFalse()
            ->closed_at->toBeNull()
            ->closed_by->toBeNull();
    });

    it('does not treat the current panel\'s closed status as closing a ticket in another panel', function () {
        (new TicketStatusSeeder)->run();

        $ticket = Ticket::factory()->open()->create(['panel' => 'test2']);

        $ticket->update(['status_id' => TicketStatus::getClosedStatus()->id]);

        expect($ticket->refresh()->isClosed)->toBeFalse();
    });
});
