<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
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

        Livewire::test(ListTickets::class, ['activeTab' => 'all'])
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

        expect(Livewire::test(ListTickets::class)->instance()->getWidgetData()['activeTab'])->toBe('my')
            ->and(Livewire::test(ListTickets::class)->set('activeTab', 'all')->instance()->getWidgetData()['activeTab'])->toBe('all');
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
            ->getDescription()->toBe('Needs a reply or an assignee')
            ->getValue()->toBe(2)
            ->and($stat(OpenSupporterTickets::class, 'linked'))
            ->getLabel()->toBe('Waiting on Platform Support')
            ->getColor()->toBe('gray')
            ->getDescription()->toBe('Escalations Platform Support owes a reply on')
            ->getValue()->toBe(1);
    });

    it('shows Needs You in gray when nothing is waiting on the viewer', function () {
        $this->login();

        expect(Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'all'])->instance()->getStats()[0])
            ->getLabel()->toBe('Needs You')
            ->getValue()->toBe(0)
            ->getColor()->toBe('gray');
    });

    it('counts the rows that need the viewer as Needs You, the same rows the list ranks first', function () {
        $me = $this->login();
        $colleague = User::factory()->create();

        $mine = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);
        $unassigned = Ticket::factory()->open()->create(['assignee_id' => null, 'turn' => Turn::Supporter]);
        Ticket::factory()->open()->create(['assignee_id' => $colleague->id, 'turn' => Turn::Supporter]);
        Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::User]);
        Ticket::factory()->closed()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);

        $component = Livewire::test(ListTickets::class, ['activeTab' => 'all']);
        $orange = collect([$mine, $unassigned])
            ->filter(fn (Ticket $ticket): bool => $component->instance()->getTableRecord((string) $ticket->id)->conversation_rank == 0);

        expect($orange)->toHaveCount(2)
            ->and(Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'all'])->instance()->getStats()[0]->getValue())->toBe(2)
            ->and(Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'my'])->instance()->getStats()[0]->getValue())->toBe(1);
    });

    it('tells the cards to count again after a row action, such as Reassign, moves a ticket out of Needs You', function () {
        $me = $this->login();
        $colleague = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);

        $needsYou = Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'all']);

        expect($needsYou->instance()->getStats()[0]->getValue())->toBe(1);

        Livewire::test(ListTickets::class)
            ->callAction(TestAction::make(ReassignTicketAction::class)->table($ticket), ['assignee_id' => $colleague->id])
            ->assertHasNoActionErrors()
            ->assertDispatched('refresh-ticket-stats')
            ->assertDispatched('refresh-sidebar');

        expect($ticket->refresh()->assignee_id)->toBe($colleague->id);

        $needsYou->dispatch('refresh-ticket-stats');

        expect($needsYou->html())->toMatch('/fi-wi-stats-overview-stat-value">\s*0\s*</');
    });

    it('explains Needs You in the same words in a panel that receives escalations', function () {
        $this->login();
        Filament::setCurrentPanel('test2');

        expect(Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'all'])->instance()->getStats()[0])
            ->getLabel()->toBe('Needs You')
            ->getDescription()->toBe('Needs a reply or an assignee');
    });

    // An icon beside a description that wrapped floated to the card's far edge, so the cards have none.
    it('draws no description icons, on any tab or panel', function (string $panel, string $tab) {
        $this->login();
        Filament::setCurrentPanel($panel);

        foreach ([OpenTicketsWidget::class, OpenSupporterTickets::class, TicketCloseTimeWidget::class] as $widget) {
            expect(Livewire::test($widget, ['activeTab' => $tab])->instance()->getStats()[0])
                ->getDescription()->not->toBeEmpty()
                ->getDescriptionIcon()->toBeNull();
        }

        expect(Livewire::test(OpenSupporterTickets::class)->instance()->getStats()[0]->getDescriptionIcon())->toBeNull();
    })->with([
        'all' => ['test', 'all'],
        'my' => ['test', 'my'],
        'escalations' => ['test', 'linked'],
        'my escalations' => ['test', 'my_linked'],
        'receiving panel' => ['test2', 'all'],
    ]);

    it('averages close time over the tab\'s own closed tickets', function () {
        $user = $this->login();

        Ticket::factory()->closed()->create(['assignee_id' => User::factory()->create()->id, 'created_at' => now()->subDays(3), 'closed_at' => now()]);

        $stat = fn (string $tab) => Livewire::test(TicketCloseTimeWidget::class, ['activeTab' => $tab, 'tableFilters' => ['open' => ['isActive' => false]]])->instance()->getStats()[0];

        expect($stat('all'))
            ->getValue()->toBe('3 days')
            ->getDescription()->toBe('1 ticket closed')
            ->and($stat('my'))
            ->getValue()->toBe('–')
            ->getDescription()->toBe('0 tickets closed');
    });

    it('counts several closed tickets in the plural', function () {
        $this->login();

        Ticket::factory()->count(2)->closed()->create(['assignee_id' => User::factory()->create()->id, 'created_at' => now()->subDay(), 'closed_at' => now()]);

        expect(Livewire::test(TicketCloseTimeWidget::class, ['activeTab' => 'all', 'tableFilters' => ['open' => ['isActive' => false]]])->instance()->getStats()[0]->getDescription())
            ->toBe('2 tickets closed');
    });

    it('stays panel-wide when used away from the list', function () {
        $user = $this->login();

        Ticket::factory()->open()->count(2)->create(['assignee_id' => User::factory()->create()->id]);

        expect(Livewire::test(OpenTicketsWidget::class)->instance()->getStats()[0]->getValue())->toBe(2);
    });
});

describe('Sidebar badge', function () {
    beforeEach(function () {
        $this->me = $this->login(User::factory()->create(['name' => 'Tess Support']));
        $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
        $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
        $this->staff = User::factory()->create(['name' => 'Kevin McKee']);

        TicketPlugin::get('test')->allSupportersQuery(fn () => User::query()->whereKey([$this->me->id, $this->colleague->id]));
        TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->staff->id));

        $message = fn (Ticket $ticket, ActivitySender $sender, ?int $userId) => TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'sender' => $sender,
            'user_id' => $userId,
        ]);

        $escalated = function (Turn $escalationTurn, bool $replied) use ($message): Ticket {
            $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'turn' => $escalationTurn, 'submitter_id' => $this->me->id, 'assignee_id' => $this->staff->id]);
            $original = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id, 'turn' => Turn::User, 'linked_ticket_id' => $escalation->id]);
            $message($original, ActivitySender::Supporter, $this->me->id);
            $message($escalation, ActivitySender::User, $this->me->id);

            if ($replied) {
                $message($escalation, ActivitySender::Supporter, $this->staff->id);
            }

            return $original;
        };

        // Waiting on you, unassigned, and a reply from the other team to pass on: they need you.
        Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id, 'turn' => Turn::Supporter]);
        Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => null, 'turn' => Turn::Supporter]);
        $escalated(Turn::User, true);
        // Waiting on the requester, and waiting on the other team: they don't.
        Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id, 'turn' => Turn::User]);
        $escalated(Turn::Supporter, false);
        Ticket::factory()->closed()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id, 'turn' => Turn::Supporter]);
    });

    $needsYouCard = fn (): int => Livewire::test(OpenSupporterTickets::class, ['activeTab' => 'all'])->instance()->getStats()[0]->getValue();

    it('counts what the Needs You card counts, in orange, in a panel that turns it on', function () use ($needsYouCard) {
        TicketPlugin::get('test')->navigationBadgeCountsNeedsYou();

        expect($needsYouCard())->toBe(3)
            ->and(TicketResource::getNavigationBadge())->toBe('3')
            ->and(TicketResource::getNavigationBadgeColor())->toBe('warning')
            ->and(TicketResource::getNavigationBadgeTooltip())->toBe('Tickets that need you: waiting on your reply, with nobody assigned, or with a reply from Platform Support to pass on');
    });

    it('counts what the Needs You card counts in a panel that receives escalations', function () use ($needsYouCard) {
        TicketPlugin::get('test2')->navigationBadgeCountsNeedsYou();
        Filament::setCurrentPanel('test2');
        $this->login($this->staff);

        // Both escalations wait on the other team or were answered by it, so a third one waits on Padmission.
        $waiting = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'turn' => Turn::Supporter, 'submitter_id' => $this->me->id, 'assignee_id' => null]);
        Ticket::factory()->open()->create(['linked_ticket_id' => $waiting->id]);

        expect(TicketResource::getNavigationBadge())->toBe((string) $needsYouCard())
            ->and($needsYouCard())->toBeGreaterThan(0)
            ->and(TicketResource::getNavigationBadgeTooltip())->toBe('Tickets that need you: waiting on your reply or with nobody assigned');
    });

    it('hides the badge at 0', function () use ($needsYouCard) {
        TicketPlugin::get('test')->navigationBadgeCountsNeedsYou();
        Ticket::query()->update(['closed_at' => now()]);

        expect($needsYouCard())->toBe(0)
            ->and(TicketResource::getNavigationBadge())->toBeNull();
    });

    it('keeps counting open tickets assigned to the viewer, with its own colour and help, in a panel that leaves it off', function () {
        expect(TicketPlugin::get('test')->shouldNavigationBadgeCountNeedsYou())->toBeFalse()
            ->and(TicketResource::getNavigationBadge())->toBe((string) TicketResource::countOpenTicketsAssignedToCurrentUser())
            ->and(TicketResource::getNavigationBadge())->toBe('4')
            ->and(TicketResource::getNavigationBadgeColor())->not->toBe('warning')
            ->and(TicketResource::getNavigationBadgeTooltip())->toBe('Open tickets assigned to you');
    });

    it('never counts Needs You for someone who only submits tickets', function () {
        TicketPlugin::get('test')->navigationBadgeCountsNeedsYou();
        $this->login($this->requester);

        expect(TicketResource::getNavigationBadge())->toBeNull();
    });
});

it('keeps every stat card in sync with the exposed tab, filters and search', function () {
    $me = $this->login();
    $colleague = User::factory()->create();
    $mine = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter, 'subject' => 'Find this ticket']);
    Ticket::factory()->open()->create(['assignee_id' => $colleague->id, 'turn' => Turn::Supporter]);
    $closed = Ticket::factory()->closed()->create(['assignee_id' => $me->id, 'created_at' => now()->subDays(3), 'closed_at' => now()]);
    Ticket::factory()->closed()->create(['assignee_id' => $colleague->id, 'created_at' => now()->subDay(), 'closed_at' => now()]);

    $page = Livewire::test(ListTickets::class)->set('activeTab', 'my');
    $stats = function () use ($page): array {
        $data = $page->instance()->getWidgetData();

        return array_map(fn (string $widget) => Livewire::test($widget, $data)->instance()->getStats()[0], [
            OpenTicketsWidget::class, OpenSupporterTickets::class, TicketCloseTimeWidget::class,
        ]);
    };

    [$open, $needsReply, $closeTime] = $stats();
    expect($open->getValue())->toBe(1)
        ->and($needsReply->getValue())->toBe(1)
        ->and($closeTime->getDescription())->toBe('0 tickets closed');

    $page->removeTableFilter('open')->filterTable('status', [$closed->status_id]);
    [$open, $needsReply, $closeTime] = $stats();
    expect($open->getValue())->toBe(0)
        ->and($needsReply->getValue())->toBe(0)
        ->and($closeTime->getValue())->toBe('3 days')
        ->and($closeTime->getDescription())->toBe('1 ticket closed');

    $page->removeTableFilter('status')->searchTable('Find this ticket');
    [$open, $needsReply, $closeTime] = $stats();
    expect($open->getValue())->toBe(1)
        ->and($needsReply->getValue())->toBe(1)
        ->and($closeTime->getDescription())->toBe('0 tickets closed');

    $page->searchTable('missing ticket');
    [$open, $needsReply, $closeTime] = $stats();
    expect($open->getValue())->toBe(0)
        ->and($needsReply->getValue())->toBe(0)
        ->and($closeTime->getDescription())->toBe('0 tickets closed');
});
