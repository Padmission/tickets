<?php

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\EscalationSummary;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\ValueObjects\SubmitterData;

it('lists open tickets whatever status they have, and hides closed ones', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $foreignStatus = TicketStatus::factory()->create(['panel' => 'test2']);

    $openWithForeignStatus = Ticket::factory()->create(['status_id' => $foreignStatus->id, 'closed_at' => null]);
    $closed = Ticket::factory()->closed()->create();

    Livewire::test(ListTickets::class)
        ->assertCanSeeTableRecords([$openWithForeignStatus])
        ->assertCanNotSeeTableRecords([$closed])
        ->removeTableFilter('open')
        ->assertCanSeeTableRecords([$openWithForeignStatus, $closed]);
});

it('only offers escalated tabs in a panel that escalates', function () {
    $this->login();

    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

    expect(TicketPlugin::get()->hasLinkedTickets())->toBeTrue()
        ->and($tabs)->not->toHaveKeys(['linked', 'my_linked']);
});

it('explains the active tab', function (string $tab, string $key) {
    $this->login();

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    Livewire::test(ListTickets::class, ['activeTab' => $tab])
        ->assertSee(__("padmission-tickets::tickets.resources.tickets.tab_descriptions.{$key}", ['team' => 'Platform Support']));
})->with([
    'all' => ['all', 'all'],
    'my' => ['my', 'my'],
    'escalated' => ['linked', 'linked_to'],
]);

it('tells someone who only submits tickets that the list is theirs, without team-wide counts', function () {
    $this->login();

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));

    Livewire::test(ListTickets::class)
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.tab_descriptions.all_submitter'))
        ->assertDontSeeLivewire(OpenTicketsWidget::class);
});

it('shows the team-wide counts to supporters', function () {
    $this->login();

    Livewire::test(ListTickets::class)
        ->assertSeeLivewire(OpenTicketsWidget::class);
});

describe('Tab badges', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
    });

    it('counts my open tickets the same as the navigation badge', function () {
        $user = $this->login();

        Ticket::factory()->open()->count(2)->create(['assignee_id' => $user->id]);
        Ticket::factory()->closed()->create(['assignee_id' => $user->id]);
        Ticket::factory()->open()->create(['assignee_id' => User::factory()->create()->id]);

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        expect($tabs['my']->getBadge())->toBe('2')
            ->and(TicketResource::getNavigationBadge())->toBe('2')
            ->and(TicketResource::getNavigationBadgeTooltip())->toBe(__('padmission-tickets::tickets.resources.tickets.badges.my'))
            ->and($tabs['all']->getBadge())->toBe('3')
            ->and($tabs['all']->getBadgeTooltip())->toBe('Open tickets in this tab')
            ->and($tabs['my']->getBadgeTooltip())->toBe('Open tickets in this tab');
    });

    it('shows 0 on every tab when there is nothing open', function () {
        $this->login();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        Ticket::factory()->closed()->create();

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        expect($tabs['all']->getBadge())->toBe('0')
            ->and($tabs['my']->getBadge())->toBe('0')
            ->and($tabs['linked']->getBadge())->toBe('0')
            ->and($tabs['my_linked']->getBadge())->toBe('0')
            ->and(TicketResource::getNavigationBadge())->toBeNull();
    });

    it('counts the open escalated tickets on each escalated tab', function () {
        $user = $this->login();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

        $escalate = fn (array $attributes, bool $open = true) => ($open ? Ticket::factory()->open() : Ticket::factory()->closed())
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2', ...$attributes]);

        $escalate(['submitter_id' => $user->id]);
        $escalate(['submitter_id' => User::factory()->create()->id]);
        $escalate(['submitter_id' => $user->id], open: false);

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        expect($tabs['linked']->getBadge())->toBe('2')
            ->and($tabs['my_linked']->getBadge())->toBe('1');
    });
});

it('finds a ticket by the number quoted from an email', function (string $prefix) {
    (new TicketStatusSeeder)->run();
    $this->login();

    $wanted = Ticket::factory()->open()->create(['subject' => 'Rent question']);
    $other = Ticket::factory()->open()->create(['subject' => 'Something else']);

    Livewire::test(ListTickets::class)
        ->searchTable($prefix.$wanted->id)
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable('rent')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
})->with(['plain' => '', 'with hash' => '#']);

it('keeps priority off until chosen and lets the subject wrap, so the list fits the screen', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->assertTableColumnExists('priority.display_name', fn (TextColumn $column): bool => $column->isToggledHiddenByDefault())
        ->assertCanNotRenderTableColumn('priority.display_name')
        ->assertTableColumnExists('subject', fn (TextColumn $column): bool => $column->canWrap());
});

it('names each ticket\'s organization under its subject in the panel escalations are sent to', function () {
    (new TicketStatusSeeder)->run();
    $this->login();
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    TicketPlugin::get()->describeTicketOriginUsing(fn (Ticket $ticket): string => "Org of {$ticket->id}");

    $plain = Ticket::factory()->open()->create();
    $escalation = Ticket::factory()->open()->create();
    Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id, 'submitter_id' => User::factory()->create(['name' => 'Aisha Brooks'])->id]);

    Livewire::test(ListTickets::class)
        ->assertTableColumnHasDescription('subject', "Org of {$plain->id}", $plain)
        ->assertTableColumnHasDescription('subject', "Org of {$escalation->id} · About Aisha Brooks's ticket", $escalation);
});

it('offers the ticket number as a sortable column that is off until chosen', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $first = Ticket::factory()->open()->create();
    $second = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->assertTableColumnExists('id', fn (TextColumn $column): bool => $column->isToggleable()
            && $column->isToggledHiddenByDefault()
            && $column->isSortable()
            && $column->getLabel() === 'Ticket #')
        ->assertCanNotRenderTableColumn('id')
        ->toggleAllTableColumns()
        ->assertCanRenderTableColumn('id')
        ->sortTable('id', 'desc')
        ->assertCanSeeTableRecords([$second, $first], inOrder: true)
        ->searchTable('#'.$first->id)
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
});

describe('Escalated tickets in the list', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('names the team an escalation is assigned within when its person is out of sight', function () {
        $this->login();

        $escalation = Ticket::factory()->open()
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2', 'assignee_id' => 999999]);
        $unassigned = Ticket::factory()->open()
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2', 'assignee_id' => null]);

        expect(TicketResource::assigneeLabel($escalation))->toBe('Platform Support')
            ->and(TicketResource::assigneeLabel($unassigned))->toBeNull()
            ->and(TicketResource::assigneeLabel(Ticket::factory()->create(['assignee_id' => 999999])))->toBeNull();

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'linked')
            ->assertTableColumnStateSet('assignee.name', 'Platform Support', $escalation);
    });

    it('names the person an escalation is assigned to, found through its team\'s panel', function () {
        $this->login();
        $person = User::factory()->create(['name' => 'Kevin McKee']);

        TicketPlugin::get('test2')->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('acting-tenant'));
        User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($person->id));

        $escalation = Ticket::factory()->open()
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2', 'assignee_id' => $person->id]);

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'linked')
            ->assertTableColumnStateSet('assignee.name', 'Kevin McKee', $escalation)
            ->call('getTableRecords')
            ->assertReturned(fn (mixed $records): bool => str_contains((string) json_encode($records), 'Kevin McKee')
                && ! str_contains((string) json_encode($records), $person->email));
    })->after(fn () => User::clearBootedModels());

    it('leaves out the team column when every escalation goes to the same team', function () {
        $this->login();

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'linked')
            ->assertTableColumnHidden('panel');
    });

    it('names each team when there is more than one to escalate to', function () {
        $this->login();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2', 'test3']);

        expect(TicketPlugin::get()->getLinkedTicketParentPanels())->toHaveCount(2);

        $escalation = Ticket::factory()->open()
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2']);

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'linked')
            ->assertTableColumnVisible('panel')
            ->assertTableColumnFormattedStateSet('panel', 'Platform Support', $escalation);
    });
});

it('says You for a ticket assigned to the viewer and names anyone else', function () {
    (new TicketStatusSeeder)->run();
    $me = $this->login();
    $colleague = User::factory()->create(['name' => 'Maria Lopez']);

    $mine = Ticket::factory()->open()->create(['assignee_id' => $me->id]);
    $theirs = Ticket::factory()->open()->create(['assignee_id' => $colleague->id]);

    Livewire::test(ListTickets::class)
        ->assertTableColumnStateSet('assignee.name', 'You', $mine)
        ->assertTableColumnStateSet('assignee.name', 'Maria Lopez', $theirs);
});

it('says You to whoever is signed in now, not to the first viewer', function () {
    (new TicketStatusSeeder)->run();
    [$first, $second] = User::factory()->count(2)->create();

    $firstTicket = Ticket::factory()->open()->create(['assignee_id' => $first->id]);
    $secondTicket = Ticket::factory()->open()->create(['assignee_id' => $second->id]);

    $this->actingAs($first);

    Livewire::test(ListTickets::class)
        ->assertTableColumnStateSet('assignee.name', 'You', $firstTicket)
        ->assertTableColumnStateSet('assignee.name', $second->name, $secondTicket);

    $this->actingAs($second);

    Livewire::test(ListTickets::class)
        ->assertTableColumnStateSet('assignee.name', $first->name, $firstTicket)
        ->assertTableColumnStateSet('assignee.name', 'You', $secondTicket);
});

it('counts every account the panel says is the viewer\'s as theirs', function () {
    (new TicketStatusSeeder)->run();
    $me = $this->login();
    $otherAccount = User::factory()->create(['name' => 'Same person, other account']);

    TicketPlugin::get()->currentUserAssigneeIds(fn (): array => [$me->id, $otherAccount->id]);

    $ticket = Ticket::factory()->open()->create(['assignee_id' => $otherAccount->id]);

    Livewire::test(ListTickets::class)
        ->assertTableColumnStateSet('assignee.name', 'You', $ticket);
});

function listCell(Testable $component, string $name, Ticket $record, string $part = 'state'): string
{
    $column = $component->instance()->getTable()->getColumn($name);
    $column->record($component->instance()->getTableRecord((string) $record->getKey()));
    $column->clearCachedState();

    return (string) match ($part) {
        'description' => $column->getDescriptionBelow(),
        'label' => $column->getLabel(),
        default => $column->formatState($column->getState()),
    };
}

describe('Conversations in the list', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');

        $this->me = $this->login(User::factory()->create(['name' => 'Test Admin']));
        $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
        $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);

        $this->message = fn (Ticket $ticket, ActivitySender $sender, ?int $userId): TicketActivity => TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'sender' => $sender,
            'user_id' => $userId,
        ]);

        $this->escalate = fn (array $original, array $escalation = []): Ticket => Ticket::factory()->open()->create([
            'panel' => 'test',
            'turn' => Turn::Supporter,
            'submitter_id' => $this->requester->id,
            'assignee_id' => $this->me->id,
            'linked_ticket_id' => Ticket::factory()->open()->create([
                'panel' => 'test2',
                'source_panel' => 'test',
                'turn' => Turn::Supporter,
                'submitter_id' => $this->me->id,
                ...$escalation,
            ])->id,
            ...$original,
        ]);
    });

    it('marks an escalated original after its subject, for supporters only', function () {
        $waitingOnTeam = ($this->escalate)([]);
        $waitingOnColleague = ($this->escalate)(['assignee_id' => $this->colleague->id], ['submitter_id' => $this->colleague->id, 'turn' => Turn::User]);
        $replied = ($this->escalate)([]);
        ($this->message)($replied->parentTicket, ActivitySender::Supporter, $this->colleague->id);
        $repliedToOther = ($this->escalate)(['assignee_id' => $this->colleague->id], ['submitter_id' => $this->colleague->id]);
        ($this->message)($repliedToOther->parentTicket, ActivitySender::Supporter, $this->me->id);
        $closed = ($this->escalate)([]);
        $closed->parentTicket->close(closedById: $this->colleague->id);
        $closed->parentTicket->forceFill(['closed_at' => now()->subDay()])->saveQuietly();
        $plain = Ticket::factory()->open()->create(['subject' => '<b>Rent</b> question']);

        $component = Livewire::test(ListTickets::class);

        expect(listCell($component, 'subject', $waitingOnTeam))->toContain('Escalated')->toContain('You asked Platform Support about this. Platform Support owes the next reply there.')
            ->and(listCell($component, 'subject', $waitingOnColleague))->toContain('Escalated')->toContain('Platform Support is waiting on Maria Lopez on the escalation.')
            ->and(listCell($component, 'subject', $replied))->toContain('Platform Support replied')->toContain('fi-color-warning')
            ->toContain('Platform Support replied on the escalation after your team last wrote. Read it, then answer Platform Support there or pass the answer on to Aisha Brooks here.')
            ->and(listCell($component, 'subject', $repliedToOther))->toMatch('/>\s*Platform Support replied\s*</')->toContain('pad-ti-marker')->not->toContain('fi-color-warning')
            ->toContain('Platform Support replied to Maria Lopez on the escalation after your team last wrote. Maria Lopez passes the answer on to Aisha Brooks.')
            ->and(listCell($component, 'subject', $closed))->toContain('Escalation closed')->toContain('The escalation was closed 1 day ago.')
            ->and(listCell($component, 'subject', $plain))->toBe('<b>Rent</b> question');

        $component->assertSee('&lt;b&gt;Rent&lt;/b&gt; question', escape: false);
    });

    it('never marks an escalation for the person who asked', function () {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKeyNot($this->me->id));
        $mine = ($this->escalate)(['submitter_id' => $this->me->id]);

        expect(listCell(Livewire::test(ListTickets::class), 'subject', $mine))->toBe($mine->subject);
    });

    it('names the tickets an escalation is about', function () {
        $named = fn (string $name): array => ['submitter_id' => User::factory()->create(['name' => $name])->id];
        $escalation = fn (array ...$originals): Ticket => tap(
            Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->me->id]),
            fn (Ticket $escalation) => collect($originals)->each(fn (array $original) => Ticket::factory()
                ->{($original['closed'] ?? false) ? 'closed' : 'open'}()
                ->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id, ...collect($original)->except('closed')->all()])),
        );

        $one = $escalation($named('Aisha Brooks'));
        $two = $escalation($named('Aisha Brooks'), $named('Felix Moreno'));
        $many = $escalation($named('Aisha Brooks'), $named('Felix Moreno'), $named('Henry Silva'));
        $someClosed = $escalation($named('Aisha Brooks'), [...$named('Felix Moreno'), 'closed' => true]);
        $allClosed = $escalation([...$named('Aisha Brooks'), 'closed' => true]);
        $emptied = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->me->id]);
        $emptied->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, $this->me->id);

        $component = Livewire::test(ListTickets::class, ['activeTab' => 'linked'])->removeTableFilter('open');

        expect(listCell($component, 'subject', $one, 'description'))->toBe('About Aisha Brooks\'s ticket')
            ->and(listCell($component, 'subject', $two, 'description'))->toBe('About Aisha Brooks\'s and Felix Moreno\'s tickets')
            ->and(listCell($component, 'subject', $many, 'description'))->toBe('About tickets from Aisha Brooks and 2 others')
            ->and(listCell($component, 'subject', $someClosed, 'description'))->toBe('About Aisha Brooks\'s and Felix Moreno\'s tickets, 1 of 2 closed')
            ->and(listCell($component, 'subject', $allClosed, 'description'))->toBe('About Aisha Brooks\'s ticket, all closed')
            ->and(listCell($component, 'subject', $emptied, 'description'))->toBe('Not linked to any ticket');

        expect(listCell(Livewire::test(ListTickets::class), 'subject', Ticket::factory()->open()->create(), 'description'))->toBe('');
    });

    it('names each requester once however many of their tickets an escalation is about', function () {
        $aisha = User::factory()->create(['name' => 'Aisha Brooks']);
        $felix = User::factory()->create(['name' => 'Felix Moreno']);
        $henry = User::factory()->create(['name' => 'Henry Silva']);
        $about = function (array $requesters): string {
            $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->me->id]);

            foreach ($requesters as $requester) {
                Ticket::factory()->open()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id, 'submitter_id' => $requester->id]);
            }

            return EscalationSummary::about($escalation->load('childTickets'));
        };

        expect($about([$aisha, $aisha]))->toBe('About Aisha Brooks\'s 2 tickets')
            ->and($about([$aisha, $aisha, $aisha]))->toBe('About Aisha Brooks\'s 3 tickets')
            ->and($about([$aisha, $felix, $aisha]))->toBe('About Aisha Brooks\'s and Felix Moreno\'s tickets')
            ->and($about([$aisha, $felix, $aisha, $henry]))->toBe('About tickets from Aisha Brooks and 2 others');

        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->me->id]);
        Ticket::factory()->count(2)->open()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id, 'submitter_id' => null, 'submitter_data' => new SubmitterData('Guest Person', 'guest@example.com')]);

        expect(EscalationSummary::about($escalation->load('childTickets')))->toBe('About Guest Person\'s 2 tickets');

        $component = Livewire::test(ListTickets::class, ['activeTab' => 'linked']);
        $listed = Ticket::query()->whereKey(Ticket::query()->where('linked_ticket_id', '!=', null)->where('submitter_id', $aisha->id)->value('linked_ticket_id'))->sole();

        expect(listCell($component, 'subject', $listed, 'description'))->toBe('About Aisha Brooks\'s 2 tickets');
    });

    it('names a guest requester by the name they gave, and counts unnamed ones', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->me->id]);
        $guest = Ticket::factory()->open()->withSubmitterData()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id]);

        expect(EscalationSummary::about($escalation->load('childTickets')))->toBe("About {$guest->submitter_data->name}'s ticket");

        $guest->update(['submitter_data' => null]);
        Ticket::factory()->open()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id]);

        expect(EscalationSummary::about($escalation->fresh()->load('childTickets')))->toBe('About 2 tickets');
    });

    it('names who handles an escalation, and who the contact is in the panel that receives it', function () {
        $escalation = Ticket::factory()->open()
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2', 'submitter_id' => $this->me->id]);
        $original = Ticket::factory()->open()->create(['submitter_id' => $this->me->id]);

        $all = Livewire::test(ListTickets::class);
        $all->assertTableColumnStateSet('submitter.name', 'You', $original)
            ->assertTableFilterExists('submitter', fn ($filter): bool => $filter->getLabel() === 'Requested by');
        expect(listCell($all, 'submitter.name', $original, 'label'))->toBe('Requested by');

        $linked = Livewire::test(ListTickets::class, ['activeTab' => 'linked']);
        $linked->assertTableColumnStateSet('submitter.name', 'You', $escalation)
            ->assertTableFilterExists('submitter', fn ($filter): bool => $filter->getLabel() === 'Handled by');
        expect(listCell($linked, 'submitter.name', $escalation, 'label'))->toBe('Handled by')
            ->and(listCell($linked, 'assignee.name', $escalation, 'label'))->toBe('Assigned to');

        Filament::setCurrentPanel('test2');
        $escalation->update(['submitter_id' => $this->colleague->id]);
        $received = Livewire::test(ListTickets::class)->assertTableColumnStateSet('submitter.name', 'Maria Lopez', $escalation);
        $received->assertTableFilterExists('submitter', fn ($filter): bool => $filter->getLabel() === 'Contact');
        $neverEscalated = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test']);
        $received = Livewire::test(ListTickets::class);
        expect(listCell($received, 'submitter.name', $escalation, 'label'))->toBe('Contact')
            ->and(listCell($received, 'subject', $escalation, 'description'))->toStartWith('About ')
            ->and(listCell($received, 'subject', $neverEscalated, 'description'))->toBe('');
    });

    it('explains who picks the assignee of an escalation', function () {
        $escalation = Ticket::factory()->open()
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2', 'submitter_id' => $this->me->id]);

        $linked = Livewire::test(ListTickets::class, ['activeTab' => 'linked']);
        $column = $linked->instance()->getTable()->getColumn('assignee.name');
        $column->record($linked->instance()->getTableRecord((string) $escalation->id));

        expect($column->getTooltip())->toBe('The Platform Support person working on this escalation. Platform Support chooses who works on it.');
    });

    it('shows New only on conversations the viewer owns', function () {
        $mine = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id]);
        $theirs = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->colleague->id]);
        ($this->message)($mine, ActivitySender::User, $this->requester->id);
        ($this->message)($theirs, ActivitySender::User, $this->requester->id);

        $myEscalation = Ticket::factory()->open()->has(Ticket::factory(['panel' => 'test']), 'childTickets')->create(['panel' => 'test2', 'submitter_id' => $this->me->id]);
        $theirEscalation = Ticket::factory()->open()->has(Ticket::factory(['panel' => 'test']), 'childTickets')->create(['panel' => 'test2', 'submitter_id' => $this->colleague->id]);
        ($this->message)($myEscalation, ActivitySender::Supporter, $this->colleague->id);
        ($this->message)($theirEscalation, ActivitySender::Supporter, $this->me->id);

        $all = Livewire::test(ListTickets::class);
        $linked = Livewire::test(ListTickets::class, ['activeTab' => 'linked']);

        expect(listCell($all, 'latestMessage.created_at', $mine))->toContain('New')->toContain('New message you haven')
            ->and(listCell($all, 'latestMessage.created_at', $theirs))->not->toContain('New')
            ->and(listCell($linked, 'latestMessage.created_at', $myEscalation))->toContain('New')
            ->and(listCell($linked, 'latestMessage.created_at', $theirEscalation))->not->toContain('New');
    });

    it('offers no bulk actions on the escalated tabs', function () {
        Ticket::factory()->open()->has(Ticket::factory(['panel' => 'test']), 'childTickets')->create(['panel' => 'test2', 'submitter_id' => $this->me->id]);
        Ticket::factory()->open()->create();

        Livewire::test(ListTickets::class)->assertTableBulkActionVisible('assign');

        foreach (['linked', 'my_linked'] as $tab) {
            $component = Livewire::test(ListTickets::class, ['activeTab' => $tab]);

            expect($component->instance()->getTable()->isSelectionEnabled())->toBeFalse();
        }
    });

    it('puts the tickets that need the viewer first, then colleagues\' and on-hold ones, then the rest', function () {
        $requesterTurn = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id, 'turn' => Turn::User]);
        $colleagues = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->colleague->id, 'turn' => Turn::Supporter]);
        $mine = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id, 'turn' => Turn::Supporter]);
        $replied = ($this->escalate)(['assignee_id' => $this->colleague->id]);
        ($this->message)($replied->parentTicket, ActivitySender::Supporter, $this->colleague->id);

        $component = Livewire::test(ListTickets::class);

        $ranks = collect([$requesterTurn, $colleagues, $mine, $replied])
            ->mapWithKeys(fn (Ticket $ticket): array => [$ticket->id => (int) $component->instance()->getTableRecord((string) $ticket->id)->conversation_rank]);

        expect($ranks->all())->toBe([$requesterTurn->id => 2, $colleagues->id => 1, $mine->id => 0, $replied->id => 0]);

        $order = $component->instance()->getTableRecords()->pluck('id')->all();

        expect(array_search($requesterTurn->id, $order))->toBeGreaterThan(array_search($colleagues->id, $order))
            ->and(array_search($colleagues->id, $order))->toBeGreaterThan(array_search($mine->id, $order))
            ->and(array_search($colleagues->id, $order))->toBeGreaterThan(array_search($replied->id, $order));

        $component->sortTable('turn', 'desc');

        expect($component->instance()->getTableRecords()->pluck('id')->first())->toBe($requesterTurn->id);
    });
});

it('runs the same number of queries for a page of 5 rows as for 25', function (string $panel, string $tab) {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');
    TicketPlugin::get('test2')->describeTicketOriginUsing(function (Ticket $ticket): ?string {
        static $organizations = [];

        return $organizations[$ticket->source_panel] ??= DB::table('users')->orderBy('id')->value('name');
    });
    $me = $this->login();
    $colleague = User::factory()->create();

    $queries = function (int $rows) use ($me, $colleague, $panel, $tab): int {
        for ($i = Ticket::query()->count() / 2; $i < $rows; $i++) {
            $requester = User::factory()->create();
            $mine = $i % 3 === 0;
            $escalation = Ticket::factory()->open()->create([
                'panel' => 'test2',
                'source_panel' => 'test',
                'submitter_id' => $mine ? $me->id : $colleague->id,
                'assignee_id' => $mine ? $me->id : $colleague->id,
                'turn' => $i % 3 === 1 ? Turn::Supporter : Turn::User,
            ]);
            $original = Ticket::factory()->open()->create([
                'panel' => 'test',
                'submitter_id' => $requester->id,
                'assignee_id' => $mine ? $me->id : $colleague->id,
                'turn' => Turn::Supporter,
                'linked_ticket_id' => $escalation->id,
            ]);
            TicketActivity::factory()->create(['ticket_id' => $original->id, 'type' => ActivityType::Message, 'sender' => ActivitySender::User, 'user_id' => $requester->id]);
            TicketActivity::factory()->create(['ticket_id' => $original->id, 'type' => ActivityType::Message, 'sender' => ActivitySender::Supporter, 'user_id' => $original->assignee_id]);
            TicketActivity::factory()->create(['ticket_id' => $escalation->id, 'type' => ActivityType::Message, 'sender' => $mine ? ActivitySender::Supporter : ActivitySender::User, 'user_id' => $escalation->submitter_id]);
        }

        Filament::setCurrentPanel($panel);
        $component = Livewire::test(ListTickets::class, ['activeTab' => $tab])->set('tableRecordsPerPage', 25);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('$refresh')->assertCountTableRecords($rows);
        DB::disableQueryLog();
        Filament::setCurrentPanel('test');

        return count(DB::getQueryLog());
    };

    $five = $queries(5);

    expect($queries(25))->toBe($five);
})->with([
    'all tickets' => ['test', 'all'],
    'escalations' => ['test', 'linked'],
    'received escalations' => ['test2', 'all'],
]);
