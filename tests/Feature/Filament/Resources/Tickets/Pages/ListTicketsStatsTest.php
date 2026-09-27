<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');
});

describe('Waiting on for a closed ticket', function () {
    it('is empty in the list and on the ticket page', function () {
        $this->login();

        $closed = Ticket::factory()->closed()->create(['turn' => Turn::Supporter]);
        $open = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);

        expect($closed->waitingOn())->toBeNull()
            ->and($open->waitingOn())->toBe(Turn::Supporter)
            ->and($closed->refresh()->turn)->toBe(Turn::Supporter);

        Livewire::test(ListTickets::class)
            ->removeTableFilter('open')
            ->assertTableColumnStateSet('turn', null, $closed)
            ->assertTableColumnStateNotSet('turn', null, $open);

        $page = Livewire::test(ViewTicket::class, ['record' => $closed->id])->instance();

        expect($page->form->getComponent('turn')->getState())->toBeNull();
    });
});

describe('Stat cards', function () {
    it('passes the active tab to the cards', function () {
        $this->login();

        expect(Livewire::test(ListTickets::class)->instance()->getWidgetData())->toBe(['activeTab' => 'all'])
            ->and(Livewire::test(ListTickets::class)->set('activeTab', 'my')->instance()->getWidgetData())->toBe(['activeTab' => 'my']);
    });

    it('counts each tab\'s open tickets the same as its badge', function () {
        $user = $this->login();

        Ticket::factory()->open()->count(2)->create(['assignee_id' => $user->id, 'turn' => Turn::Supporter]);
        Ticket::factory()->open()->create(['assignee_id' => User::factory()->create()->id, 'turn' => Turn::User]);
        Ticket::factory()->open()
            ->has(Ticket::factory()->open()->state(['panel' => 'test', 'assignee_id' => null, 'turn' => Turn::User]), 'childTickets')
            ->create(['panel' => 'test2', 'submitter_id' => $user->id, 'turn' => Turn::Supporter]);

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        $stat = fn (string $widget, string $tab) => Livewire::test($widget, ['activeTab' => $tab])->instance()->getStats()[0];

        // All counts the escalation's original too, which lives in this panel.
        foreach (['all' => 4, 'my' => 2, 'linked' => 1, 'my_linked' => 1] as $tab => $open) {
            expect($tabs[$tab]->getBadge())->toBe((string) $open)
                ->and($stat(OpenTicketsWidget::class, $tab))
                ->getValue()->toBe($open)
                ->getColor()->toBe('gray')
                ->getDescription()->toBe(str_contains($tab, 'linked') ? 'Escalations not yet closed' : 'Tickets not yet closed');
        }

        expect($stat(OpenSupporterTickets::class, 'all'))
            ->getLabel()->toBe('Needs You')
            ->getColor()->toBe('warning')
            ->getDescription()->toBe('Open tickets waiting on your reply, with nobody assigned, or with a reply from Platform Support to pass on')
            ->getValue()->toBe(2)
            ->and($stat(OpenSupporterTickets::class, 'linked'))
            ->getLabel()->toBe('Waiting on Platform Support')
            ->getColor()->toBe('gray')
            ->getDescription()->toBe('Open escalations where Platform Support owes the next reply')
            ->getValue()->toBe(1);
    });

    it('counts the rows that need the viewer as Needs You, the same rows the list ranks first', function () {
        $me = $this->login();
        $colleague = User::factory()->create();

        $mine = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);
        $unassigned = Ticket::factory()->open()->create(['assignee_id' => null, 'turn' => Turn::Supporter]);
        Ticket::factory()->open()->create(['assignee_id' => $colleague->id, 'turn' => Turn::Supporter]);
        Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::User]);
        Ticket::factory()->closed()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);

        $component = Livewire::test(ListTickets::class);
        $orange = collect([$mine, $unassigned])
            ->filter(fn (Ticket $ticket): bool => $component->instance()->getTableRecord((string) $ticket->id)->conversation_rank == 0);

        expect($orange)->toHaveCount(2)
            ->and(Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'all'])->instance()->getStats()[0]->getValue())->toBe(2)
            ->and(Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'my'])->instance()->getStats()[0]->getValue())->toBe(1);
    });

    it('explains Needs You without a team to pass replies on from in a panel that receives escalations', function () {
        $this->login();
        Filament::setCurrentPanel('test2');

        expect(Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'all'])->instance()->getStats()[0])
            ->getLabel()->toBe('Needs You')
            ->getDescription()->toBe('Open tickets waiting on your reply or with nobody assigned');
    });

    it('averages close time over the tab\'s own closed tickets', function () {
        $user = $this->login();

        Ticket::factory()->closed()->create(['assignee_id' => User::factory()->create()->id, 'created_at' => now()->subDays(3), 'closed_at' => now()]);

        $stat = fn (string $tab) => Livewire::test(TicketCloseTimeWidget::class, ['activeTab' => $tab])->instance()->getStats()[0];

        expect($stat('all'))
            ->getValue()->toBe('3 days')
            ->getDescription()->toBe('1 tickets closed')
            ->and($stat('my'))
            ->getValue()->toBe('–')
            ->getDescription()->toBe('0 tickets closed');
    });

    it('stays panel-wide when used away from the list', function () {
        $user = $this->login();

        Ticket::factory()->open()->count(2)->create(['assignee_id' => User::factory()->create()->id]);

        expect(Livewire::test(OpenTicketsWidget::class)->instance()->getStats()[0]->getValue())->toBe(2);
    });
});
