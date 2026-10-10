<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
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

it('offers no Direct questions filter when feature disabled', function () {
    $this->login();

    $component = Livewire::test(ListTickets::class);

    expect(array_keys($component->instance()->getTabs()))->toBe(['all', 'my']);
    $component->assertTableFilterHidden('direct_questions');
});

it('keeps the two tabs and offers the Direct questions filter when feature enabled', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

    $this->login();

    $component = Livewire::test(ListTickets::class);

    expect(array_keys($component->instance()->getTabs()))->toBe(['all', 'my']);
    $component->assertTableFilterVisible('direct_questions');
});

it('lists only the questions this panel asked directly in the Direct questions view', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

    (new TicketStatusSeeder)->run();

    $this->login();

    $asked = directQuestionFrom();
    $fromElsewhere = directQuestionFrom('other-panel');
    $escalation = escalationFrom();
    $filedThere = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']);
    $own = Ticket::factory()->create();

    listDirectQuestions()
        ->assertTableColumnHidden('panel')
        ->assertTableColumnHidden('source_panel')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$asked])
        ->assertCanNotSeeTableRecords([$fromElsewhere, $escalation, $filedThere, $own]);
});

it('limits My Tickets in the Direct questions view to the ones the viewer asked', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

    (new TicketStatusSeeder)->run();

    $user = $this->login();

    $mine = directQuestionFrom(attributes: ['submitter_id' => $user->id]);
    $colleagues = directQuestionFrom(attributes: ['submitter_id' => User::factory()->create()->id]);

    listDirectQuestions('my')
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$colleagues]);

    listDirectQuestions('all')->assertCanSeeTableRecords([$mine, $colleagues]);
});

describe('Direct questions view', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('explains each tab and view in a line', function (string $tab, bool $direct, string $subheading) {
        $this->login();

        $component = Livewire::test(ListTickets::class, ['activeTab' => $tab]);

        if ($direct) {
            $component->set('tableFilters.direct_questions.isActive', true);
        }

        expect($component->instance()->getSubheading())->toBe($subheading);
    })->with([
        'all' => ['all', false, 'Every ticket your team handles.'],
        'my' => ['my', false, 'Tickets assigned to you.'],
        'all direct' => ['all', true, 'Direct questions your team asked Platform Support.'],
        'my direct' => ['my', true, 'Direct questions you asked Platform Support.'],
    ]);

    it('explains the tabs of a panel that receives escalations', function (string $tab, string $subheading) {
        $this->login();
        Filament::setCurrentPanel('test2');

        expect(Livewire::test(ListTickets::class, ['activeTab' => $tab])->instance()->getSubheading())->toBe($subheading);
    })->with([
        'all' => ['all', 'Escalations and direct questions sent to your team.'],
        'my' => ['my', 'Escalations and direct questions assigned to you.'],
    ]);

    it('shows only open direct questions by default, as many as the tab badges count', function () {
        $this->login();

        $open = [directQuestionFrom(), directQuestionFrom()];
        $closed = directQuestionFrom(state: 'closed');

        $component = listDirectQuestions()
            ->assertTableFilterVisible('open')
            ->assertCanSeeTableRecords($open)
            ->assertCanNotSeeTableRecords([$closed])
            ->assertCountTableRecords(2);

        expect($component->instance()->getTabs()['all']->getBadge())->toBe('2');

        $component->removeTableFilter('open')->assertCanSeeTableRecords([$closed]);

        expect($component->instance()->getTabs()['all']->getBadge())->toBe('3');
    });

    it('says no tickets match when the view is empty', function () {
        $this->login();

        listDirectQuestions()
            ->assertSee('No tickets match the current filters')
            ->assertSee('Clear a filter or the search to see more tickets.');
    });
});

describe('Empty state', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
    });

    it('says nothing is assigned only when no filter is hiding anything', function () {
        $this->login();

        Livewire::test(ListTickets::class, ['activeTab' => 'my'])
            ->assertSee('Nothing assigned to you')
            ->assertDontSee('No tickets match the current filters');
    });

    it('says no tickets match when a filter or the search hides records', function (Closure $narrow) {
        $user = $this->login();
        Ticket::factory()->open()->create(['assignee_id' => $user->id, 'subject' => 'Rent question']);

        $narrow(Livewire::test(ListTickets::class, ['activeTab' => 'my']))
            ->assertCountTableRecords(0)
            ->assertSee('No tickets match the current filters')
            ->assertDontSee('Nothing assigned to you');
    })->with([
        'a filter' => [fn ($page) => $page->set('tableFilters.overdue.isActive', true)],
        'the search' => [fn ($page) => $page->searchTable('Parking')],
    ]);
});
