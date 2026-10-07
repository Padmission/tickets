<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    foreach (['test', 'test2'] as $panel) {
        TicketDisposition::factory()->create(['display_name' => 'Resolved', 'panel' => $panel]);
        TicketDisposition::factory()->create(['display_name' => 'Duplicate', 'panel' => $panel]);
    }

    $this->me = $this->login();
});

function bulkClose(): TestAction
{
    return TestAction::make('close-tickets')->table()->bulk();
}

function closedShape(Ticket $ticket): array
{
    $ticket->refresh();

    return [
        'status_id' => $ticket->status_id,
        'disposition' => $ticket->disposition?->display_name,
        'closed_by' => (string) $ticket->closed_by,
        'closed' => $ticket->isClosed,
        'activities' => $ticket->ticketActivities()->orderBy('id')->get()->map(fn ($activity) => $activity->type)->all(),
    ];
}

it('closes the open tickets the viewer may close and skips the rest, saying how many of each', function () {
    $open = Ticket::factory()->open()->count(2)->create();
    $alreadyClosed = Ticket::factory()->closed()->create();
    $theirOwn = Ticket::factory()->open()->create(['submitter_id' => $this->me->id]);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->removeTableFilter('open')
        ->selectTableRecords([...$open, $alreadyClosed, $theirOwn])
        ->callAction(bulkClose(), ['disposition' => 'Resolved'])
        ->assertHasNoFormErrors()
        ->assertNotified('Closed 2 tickets. 2 were skipped.');

    expect($open->every(fn (Ticket $ticket): bool => $ticket->refresh()->isClosed))->toBeTrue()
        ->and($theirOwn->refresh()->isClosed)->toBeFalse()
        ->and($alreadyClosed->refresh()->closed_by)->not->toBe($this->me->id);
});

it('leaves each ticket exactly as its own Close dialog would', function () {
    [$bulk, $single] = Ticket::factory()->open()->count(2)->create();

    Livewire::test(ViewTicket::class, ['record' => $single->id])
        ->callAction(CloseTicketAction::class, ['disposition' => TicketDisposition::query()->where('panel', 'test')->where('display_name', 'Resolved')->value('id')]);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$bulk])
        ->callAction(bulkClose(), ['disposition' => 'Resolved'])
        ->assertNotified('Closed 1 ticket.');

    expect(closedShape($bulk))->toBe(closedShape($single))
        ->and(closedShape($bulk)['activities'])->toContain(ActivityType::Closed);
});

it('requires a disposition, as a single close does', function () {
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$ticket])
        ->callAction(bulkClose(), ['disposition' => null])
        ->assertHasFormErrors(['disposition' => 'required']);

    expect($ticket->refresh()->isClosed)->toBeFalse();
});

it('raises one close per ticket, so each recipient hears of each ticket once', function () {
    Event::fake([TicketClosedEvent::class]);
    $tickets = Ticket::factory()->open()->count(3)->create();

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords($tickets)
        ->callAction(bulkClose(), ['disposition' => 'Resolved']);

    Event::assertDispatchedTimes(TicketClosedEvent::class, 3);

    foreach ($tickets as $ticket) {
        Event::assertDispatched(TicketClosedEvent::class, fn (TicketClosedEvent $event): bool => $event->ticket->is($ticket));
    }
});

it('closes escalations in the panel that received them and leaves their originals open', function () {
    TicketPlugin::get('test')->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->allSupportersQuery(fn () => $this->me->newQuery()->whereKey($this->me->id));
    Filament::setCurrentPanel('test2');

    $escalations = collect([escalationFrom(), escalationFrom()]);
    $originals = $escalations->map(fn (Ticket $escalation): Ticket => Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]));

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords($escalations)
        ->mountAction(bulkClose())
        ->assertMountedActionModalSee('Each escalation is closed as it would be on its own: its original tickets stay open.');

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords($escalations)
        ->callAction(bulkClose(), ['disposition' => 'Duplicate'])
        ->assertNotified('Closed 2 tickets.');

    expect($escalations->every(fn (Ticket $escalation): bool => $escalation->refresh()->isClosed && $escalation->disposition->panel === 'test2'))->toBeTrue()
        ->and($originals->every(fn (Ticket $original): bool => ! $original->refresh()->isClosed))->toBeTrue();
});

it('offers no bulk actions on the organization\'s escalation tabs', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $escalation = escalationFrom(attributes: ['submitter_id' => $this->me->id]);
    Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

    Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
        ->assertActionHidden(bulkClose());
});
