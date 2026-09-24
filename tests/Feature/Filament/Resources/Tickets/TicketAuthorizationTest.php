<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    Gate::policy(Ticket::class, TicketPolicy::class);
});

it('scopes the list query to own submitted tickets for a non-supporter', function () {
    (new TicketStatusSeeder)->run();

    [$outsider, $supporter, $stranger] = User::factory()->count(3)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $openStatusId = TicketStatus::getOpenStatuses()->first()->id;

    $own = Ticket::factory()->open()->create(['submitter_id' => $outsider->id, 'status_id' => $openStatusId]);
    $foreign = Ticket::factory()->open()->create(['submitter_id' => $stranger->id, 'status_id' => $openStatusId]);

    $this->actingAs($outsider);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords([$own->id])
        ->assertCanNotSeeTableRecords([$foreign->id]);
});

it('still scopes a non-supporter to their own tickets when an invalid tab is supplied', function () {
    (new TicketStatusSeeder)->run();

    [$outsider, $supporter, $stranger] = User::factory()->count(3)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $openStatusId = TicketStatus::getOpenStatuses()->first()->id;

    $own = Ticket::factory()->open()->create(['submitter_id' => $outsider->id, 'status_id' => $openStatusId]);
    $foreign = Ticket::factory()->open()->create(['submitter_id' => $stranger->id, 'status_id' => $openStatusId]);

    $this->actingAs($outsider);

    Livewire::test(ListTickets::class, ['activeTab' => 'bogus'])
        ->assertCanSeeTableRecords([$own->id])
        ->assertCanNotSeeTableRecords([$foreign->id]);

    Livewire::test(ListTickets::class)
        ->set('activeTab', 'does-not-exist')
        ->assertCanSeeTableRecords([$own->id])
        ->assertCanNotSeeTableRecords([$foreign->id]);
});

it('does not leak another panel\'s tickets to a supporter on an invalid tab', function () {
    (new TicketStatusSeeder)->run();

    [$supporter, $submitterA, $submitterB] = User::factory()->count(3)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $openStatusId = TicketStatus::getOpenStatuses()->first()->id;

    $currentPanelTicket = Ticket::factory()->open()->create([
        'panel' => 'test',
        'submitter_id' => $submitterA->id,
        'status_id' => $openStatusId,
    ]);

    $otherPanelTicket = Ticket::factory()->open()->create([
        'panel' => 'test2',
        'submitter_id' => $submitterB->id,
        'status_id' => $openStatusId,
    ]);

    $this->actingAs($supporter);

    Livewire::test(ListTickets::class, ['activeTab' => 'bogus'])
        ->assertCanSeeTableRecords([$currentPanelTicket->id])
        ->assertCanNotSeeTableRecords([$otherPanelTicket->id]);

    Livewire::test(ListTickets::class)
        ->set('activeTab', 'does-not-exist')
        ->assertCanSeeTableRecords([$currentPanelTicket->id])
        ->assertCanNotSeeTableRecords([$otherPanelTicket->id]);
});

it('does not delete a ticket the user is not authorized to delete', function () {
    (new TicketStatusSeeder)->run();

    [$outsider, $supporter, $stranger] = User::factory()->count(3)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $openStatusId = TicketStatus::getOpenStatuses()->first()->id;

    $own = Ticket::factory()->open()->create(['submitter_id' => $outsider->id, 'status_id' => $openStatusId]);
    $foreign = Ticket::factory()->open()->create(['submitter_id' => $stranger->id, 'status_id' => $openStatusId]);

    $this->actingAs($outsider);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$own->id, $foreign->id])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect($foreign->fresh()->trashed())->toBeFalse();
});

it('deletes tickets for a genuine supporter', function () {
    (new TicketStatusSeeder)->run();

    [$submitter, $supporter] = User::factory()->count(2)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $openStatusId = TicketStatus::getOpenStatuses()->first()->id;

    $ticket = Ticket::factory()->open()->create(['submitter_id' => $submitter->id, 'status_id' => $openStatusId]);

    $this->actingAs($supporter);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$ticket->id])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect($ticket->fresh()->trashed())->toBeTrue();
});

it('lets a genuine supporter see every panel ticket in the list query', function () {
    (new TicketStatusSeeder)->run();

    [$supporter, $submitterA, $submitterB] = User::factory()->count(3)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $openStatusId = TicketStatus::getOpenStatuses()->first()->id;

    $ticketA = Ticket::factory()->open()->create(['submitter_id' => $submitterA->id, 'status_id' => $openStatusId]);
    $ticketB = Ticket::factory()->open()->create(['submitter_id' => $submitterB->id, 'status_id' => $openStatusId]);

    $this->actingAs($supporter);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords([$ticketA->id, $ticketB->id]);
});

it('does not reassign a ticket the user is not authorized to manage', function () {
    (new TicketStatusSeeder)->run();

    [$submitter, $supporter] = User::factory()->count(2)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $ticket = Ticket::factory()
        ->open()
        ->create([
            'submitter_id' => $submitter->id,
            'assignee_id' => null,
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
        ]);

    $this->actingAs($submitter);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$ticket->id])
        ->assertActionHidden(TestAction::make('assign')->table()->bulk());

    expect($ticket->fresh()->assignee_id)->toBeNull();
});

it('reassigns tickets for a genuine supporter', function () {
    (new TicketStatusSeeder)->run();

    [$submitter, $supporter] = User::factory()->count(2)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $ticket = Ticket::factory()
        ->open()
        ->create([
            'submitter_id' => $submitter->id,
            'assignee_id' => null,
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
        ]);

    $this->actingAs($supporter);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$ticket->id])
        ->callAction(TestAction::make('assign')->table()->bulk(), ['assignee_id' => $supporter->id]);

    expect($ticket->fresh()->assignee_id)->toBe($supporter->id);
});

it('checks the manage ability when authorizing the bulk assign action', function () {
    (new TicketStatusSeeder)->run();

    $submitter = User::factory()->create();

    $ticket = Ticket::factory()->open()->create([
        'submitter_id' => $submitter->id,
        'status_id' => TicketStatus::getOpenStatuses()->first()->id,
    ]);

    $this->actingAs($submitter);

    $component = Livewire::test(ListTickets::class, ['activeTab' => 'all']);

    $abilities = [];
    Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$abilities) {
        $abilities[] = $event->ability;
    });

    $component
        ->selectTableRecords([$ticket->id])
        ->assertActionHidden(TestAction::make('assign')->table()->bulk());

    expect($abilities)->toContain('manage');
});

it('reassigns only the selected tickets the user may manage', function () {
    (new TicketStatusSeeder)->run();

    [$submitter, $supporter] = User::factory()->count(2)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    // A supporter may not manage a ticket they submitted themselves.
    [$mine, $theirs] = collect([$submitter, $supporter])->map(fn (User $ticketSubmitter) => Ticket::factory()->open()->create([
        'submitter_id' => $ticketSubmitter->id,
        'assignee_id' => null,
        'status_id' => TicketStatus::getOpenStatuses()->first()->id,
    ]));

    $this->actingAs($supporter);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$mine->id, $theirs->id])
        ->callAction(TestAction::make('assign')->table()->bulk(), ['assignee_id' => $supporter->id])
        ->assertNotified(__('padmission-tickets::tickets.resources.tickets.unauthorized_assignment'));

    expect($mine->fresh()->assignee_id)->toBe($supporter->id)
        ->and($theirs->fresh()->assignee_id)->toBeNull();
});
