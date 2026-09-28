<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

it('shows all and my tickets tab', function () {
    $this->login();

    $component = Livewire::test(ListTickets::class);
    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKeys(['all', 'my']);
    expect($tabs['all']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.all'));
    expect($tabs['my']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.my'));
});

it('shows all tickets in "all" tab', function () {
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

    $tickets = $ticketModel::factory()
        ->sequence(
            ['assignee_id' => $user->id],
            ['assignee_id' => $otherUser->id],
            ['assignee_id' => null],
        )
        ->count(3)
        ->open()
        ->create([
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
        ]);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->assertTableColumnHidden('panel')
        ->assertCountTableRecords(3)
        ->assertCanSeeTableRecords([$tickets->first()->id]);
});

it('shows my tickets for every id returned by the assignee resolver', function () {
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    TicketPlugin::get()->currentUserAssigneeIds(fn (): array => [$user->id, $otherUser->id]);

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

    $tickets = $ticketModel::factory()
        ->sequence(
            ['assignee_id' => $user->id],
            ['assignee_id' => $otherUser->id],
            ['assignee_id' => null],
        )
        ->count(3)
        ->open()
        ->create([
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
        ]);

    Livewire::test(ListTickets::class, ['activeTab' => 'my'])
        ->assertCountTableRecords(2)
        ->assertCanSeeTableRecords([$tickets[0]->id, $tickets[1]->id]);
});

it('shows my tickets tab filtered by assignee', function () {
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

    $tickets = $ticketModel::factory()
        ->sequence(
            ['assignee_id' => $user->id],
            ['assignee_id' => $otherUser->id],
            ['assignee_id' => null],
        )
        ->count(3)
        ->open()
        ->create([
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
        ]);

    Livewire::test(ListTickets::class, ['activeTab' => 'my'])
        ->assertTableColumnHidden('panel')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$tickets->first()->id]);
});

it('hides linked tickets tabs when feature disabled', function () {
    $this->login();

    $component = Livewire::test(ListTickets::class);
    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKeys(['all', 'my']);
    expect($tabs)->not->toHaveKeys(['linked', 'my_linked']);
});

it('shows linked tickets tabs when feature enabled', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test']);

    $this->login();

    $component = Livewire::test(ListTickets::class);
    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKeys(['all', 'my', 'linked', 'my_linked']);

    expect($tabs['linked']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.linked'));
    expect($tabs['my_linked']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'));
});

it('linked tickets only shows tickets that have a child ticket from the current panel', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

    (new TicketStatusSeeder)->run();

    $this->login();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
    $currentPanel = Filament::getCurrentPanel();

    $linkedTicket = $ticketModel::factory()
        ->has(Ticket::factory(['panel' => $currentPanel->getId()]), 'childTickets')
        ->create(['panel' => 'test2']);

    $ticketModel::factory()
        ->has(Ticket::factory(['panel' => 'other-panel']), 'childTickets')
        ->create(['panel' => 'test2']);

    $ticketModel::factory()->create();

    Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
        ->assertTableColumnHidden('panel')
        ->assertTableColumnHidden('source_panel')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$linkedTicket->id]);
});

it('filters my linked tickets tab by linked ticket id and submitter', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
    $currentPanel = Filament::getCurrentPanel();

    $linkedTickets = $ticketModel::factory()
        ->has(Ticket::factory(['panel' => $currentPanel->getId()]), 'childTickets')
        ->sequence(
            ['submitter_id' => $user->id],
            ['submitter_id' => $otherUser->id],
        )
        ->create(['panel' => 'test2']);

    Livewire::test(ListTickets::class, ['activeTab' => 'my_linked'])
        ->assertTableColumnHidden('panel')
        ->assertTableColumnHidden('source_panel')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$linkedTickets->first()->id]);
});

describe('Escalations tabs', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('calls the escalated tabs Escalations and explains each tab', function (string $tab, string $subheading) {
        $this->login();

        $component = Livewire::test(ListTickets::class, ['activeTab' => $tab]);

        expect($component->instance()->getTabs()['linked']->getLabel())->toBe('Escalations')
            ->and($component->instance()->getTabs()['my_linked']->getLabel())->toBe('My Escalations')
            ->and($component->instance()->getSubheading())->toBe($subheading);
    })->with([
        'all' => ['all', 'Conversations with the people who asked for help. Tickets that need you come first.'],
        'my' => ['my', 'Tickets assigned to you. Tickets that need you come first. Use Reassign to hand one to a teammate.'],
        'linked' => ['linked', 'Your team\'s conversations with Platform Support. Answer the requesters on their own tickets, under All Tickets.'],
        'my_linked' => ['my_linked', 'Your conversations with Platform Support. Use Hand over when a colleague should take one.'],
    ]);

    it('explains the tabs of a panel that receives escalations', function (string $tab, string $subheading) {
        $this->login();
        Filament::setCurrentPanel('test2');

        expect(Livewire::test(ListTickets::class, ['activeTab' => $tab])->instance()->getSubheading())->toBe($subheading);
    })->with([
        'all' => ['all', 'Escalations sent to your team, one conversation per escalation. Tickets that need you come first.'],
        'my' => ['my', 'Escalations assigned to you. Tickets that need you come first. Use Reassign to hand one to a teammate.'],
    ]);

    it('keeps listing an escalation whose originals were all removed, but not a ticket that was never one', function () {
        $user = $this->login();

        $emptied = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $user->id]);
        $emptied->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, $user->id);
        $filedThere = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $user->id]);

        foreach (['linked', 'my_linked'] as $tab) {
            Livewire::test(ListTickets::class, ['activeTab' => $tab])
                ->assertCanSeeTableRecords([$emptied])
                ->assertCanNotSeeTableRecords([$filedThere]);
        }

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        expect($tabs['linked']->getBadge())->toBe('1')
            ->and($tabs['my_linked']->getBadge())->toBe('1');
    });

    it('shows only open escalations by default, as many as the badge counts', function () {
        $this->login();

        $escalate = fn ($factory) => $factory
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2']);

        $open = $escalate(Ticket::factory()->open()->count(2));
        $closed = $escalate(Ticket::factory()->closed());

        $component = Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
            ->assertTableFilterVisible('open')
            ->assertCanSeeTableRecords($open)
            ->assertCanNotSeeTableRecords([$closed])
            ->assertCountTableRecords((int) Livewire::test(ListTickets::class)->instance()->getTabs()['linked']->getBadge())
            ->removeTableFilter('open')
            ->assertCanSeeTableRecords([$closed]);

        expect($component->instance()->getTabs()['linked']->getBadge())->toBe('2');
    });

    it('says there are no escalations when there are none', function () {
        $this->login();

        Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
            ->assertSee('No escalations')
            ->assertSee('When your team escalates a ticket, the conversation about it appears here.');

        Livewire::test(ListTickets::class, ['activeTab' => 'my_linked'])
            ->assertSee('You have no escalations')
            ->assertSee('Escalations you start or take over appear here.');
    });
});
