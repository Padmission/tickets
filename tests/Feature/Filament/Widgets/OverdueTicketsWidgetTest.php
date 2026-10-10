<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\OverdueTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(14, 0));
});

function overdueCardTicket(array $attributes = []): Ticket
{
    $ticket = Ticket::factory()->open()->create(['turn' => Turn::Supporter, ...$attributes]);
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'created_at' => '2026-10-02 13:59:59',
    ]);

    return $ticket;
}

it('counts the current filtered list and enables the overdue filter with its filters and search', function (string $panel) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    $mine = overdueCardTicket(['panel' => $panel, 'assignee_id' => $me->id, 'subject' => 'Reply to this']);
    $other = overdueCardTicket(['panel' => $panel, 'assignee_id' => User::factory()->create()->id]);
    overdueCardTicket(['panel' => $panel === 'test' ? 'test2' : 'test']);
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all']);
    $stat = fn () => Livewire::test(OverdueTicketsWidget::class, $page->instance()->getWidgetData())->instance()->getStats()[0];
    expect($stat()->getValue())->toBe(2);
    $page->set('activeTab', 'my');
    expect($stat()->getValue())->toBe(1);
    $page->set('activeTab', 'all')->filterTable('assignee', $me->id)->searchTable('Reply to this');
    $card = $stat();
    expect($card->getValue())->toBe(1)
        ->and($card->getLabel())->toBe('Overdue')
        ->and($card->getDescription())->toBe('Reply overdue')
        ->and($card->getColor())->toBe('danger')
        ->and($card->getDescriptionIcon())->toBeNull();

    parse_str(parse_url($card->getUrl(), PHP_URL_QUERY), $parameters);
    expect($parameters['tab'])->toBe('all')
        ->and($parameters['search'])->toBe('Reply to this');
    Livewire::withQueryParams($parameters)->test(ListTickets::class)
        ->assertSet('activeTab', 'all')
        ->assertSet('tableFilters.overdue.isActive', true)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
})->with(['organization' => 'test', 'receiving team' => 'test2']);

it('keeps the Direct questions view and the current tab when the overdue card is followed', function (string $tab) {
    $me = $this->login(User::factory()->create(['name' => 'Priya Nair']));
    $mine = directQuestionFrom(attributes: ['submitter_id' => $me->id, 'turn' => Turn::Supporter]);
    $other = directQuestionFrom(attributes: ['submitter_id' => User::factory()->create()->id, 'turn' => Turn::Supporter]);
    foreach ([$mine, $other] as $ticket) {
        TicketActivity::factory()->create(['ticket_id' => $ticket->id, 'type' => ActivityType::Message, 'sender' => ActivitySender::User, 'created_at' => '2026-10-02 13:59:59']);
    }
    $page = listDirectQuestions($tab)->searchTable('Priya Nair');
    $card = Livewire::test(OverdueTicketsWidget::class, $page->instance()->getWidgetData())->instance()->getStats()[0];
    expect($card->getValue())->toBe(1);
    parse_str(parse_url($card->getUrl(), PHP_URL_QUERY), $parameters);
    expect($parameters['tab'])->toBe($tab);
    Livewire::withQueryParams($parameters)->test(ListTickets::class)
        ->assertSet('activeTab', $tab)
        ->assertSet('tableFilters.overdue.isActive', true)
        ->assertSet('tableFilters.direct_questions.isActive', true)
        ->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);
})->with(['all', 'my']);

it('shows zero in gray and refreshes as the weekday deadline passes', function () {
    $this->login();
    $ticket = overdueCardTicket();
    $this->travelTo(now()->setTime(13, 59, 59));
    $widget = Livewire::test(OverdueTicketsWidget::class, ['activeTab' => 'all']);
    expect($widget->instance()->getStats()[0]->getValue())->toBe(0)
        ->and($widget->instance()->getStats()[0]->getColor())->toBe('gray');
    $this->travelTo(now()->addSecond());
    $widget->dispatch('refresh-ticket-stats');
    expect($widget->instance()->getStats()[0]->getValue())->toBe(1);
    $ticket->update(['turn' => Turn::User]);
    $widget->dispatch('refresh-ticket-stats');
    expect($widget->instance()->getStats()[0]->getValue())->toBe(0);
});

it('registers four equally sized header cards for supporters and follows optional dashboard registration', function () {
    $this->login();
    $widgets = [OpenTicketsWidget::class, OpenSupporterTickets::class, TicketCloseTimeWidget::class, OverdueTicketsWidget::class];
    expect(Livewire::test(ListTickets::class)->instance()->getVisibleHeaderWidgets())->toBe($widgets)
        ->and(TicketResource::getWidgets())->toBe($widgets);
    foreach ($widgets as $widget) {
        expect(Livewire::test($widget)->instance()->getColumnSpan())->toBe(3);
    }
    $panel = Panel::make()->id('dashboard');
    TicketPlugin::make()->allSupportersQuery(fn () => User::query())->registerResources(shouldRegisterWidgets: true)->register($panel);
    expect($panel->getWidgets())->toContain(OverdueTicketsWidget::class);
});

it('keeps the overdue card off the list for requesters', function () {
    $this->login();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));
    expect(Livewire::test(ListTickets::class)->instance()->getVisibleHeaderWidgets())->toBe([]);
});

it('counts accessible panel tickets on a dashboard and uses the configurable overdue scope', function () {
    $this->login();
    $ticket = overdueCardTicket();
    overdueCardTicket(['panel' => 'test2']);
    TicketPlugin::get()->customizeTicketQuery(fn ($query) => $query->whereKey($ticket->id));
    expect(Livewire::test(OverdueTicketsWidget::class)->instance()->getStats()[0]->getValue())->toBe(1);
    config()->set('padmission-tickets.overdue.business_days', 2);
    expect(Livewire::test(OverdueTicketsWidget::class)->instance()->getStats()[0]->getValue())->toBe(0);
    Livewire::test(ListTickets::class, ['activeTab' => 'all'])->filterTable('overdue')->assertCountTableRecords(0);
});

it('opens exactly the overdue intersection of the current tab while preserving filters and search', function (string $panel, string $tab, int $expectedCount) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    $colleague = User::factory()->create();
    $attributes = ['panel' => $panel, 'submitter_id' => $me->id, 'subject' => 'Queue match'];
    overdueCardTicket([...$attributes, 'assignee_id' => $me->id]);
    overdueCardTicket([...$attributes, 'assignee_id' => $colleague->id]);
    overdueCardTicket([...$attributes, 'assignee_id' => null]);
    overdueCardTicket([...$attributes, 'assignee_id' => $me->id, 'turn' => Turn::User]);
    $fresh = overdueCardTicket([...$attributes, 'assignee_id' => $me->id]);
    $fresh->ticketActivities()->where('type', ActivityType::Message)->update(['created_at' => now()]);
    overdueCardTicket([...$attributes, 'assignee_id' => $me->id, 'subject' => 'Different subject']);

    foreach ([$me, $colleague] as $owner) {
        $escalation = escalationFrom(attributes: [
            'submitter_id' => $owner->id,
            'assignee_id' => $owner->id,
            'subject' => 'Queue match',
            'turn' => Turn::Supporter,
        ]);
        TicketActivity::factory()->create([
            'ticket_id' => $escalation->id,
            'type' => ActivityType::Message,
            'sender' => ActivitySender::User,
            'created_at' => '2026-10-02 13:59:59',
        ]);
    }

    $page = Livewire::test(ListTickets::class)->set('activeTab', $tab)
        ->removeTableFilter('open')
        ->searchTable('Queue match');
    $card = Livewire::test(OverdueTicketsWidget::class, $page->instance()->getWidgetData())->instance()->getStats()[0];
    expect($card->getValue())->toBe($expectedCount);
    parse_str(parse_url($card->getUrl(), PHP_URL_QUERY), $parameters);

    // Follow the URL before checking its parameters: dropping a tab constraint
    // must fail on the actual rows.
    $destination = Livewire::withQueryParams($parameters)->test(ListTickets::class);
    $destination->assertCountTableRecords($card->getValue())
        ->assertSet('activeTab', $tab)
        ->assertSet('tableFilters.overdue.isActive', true)
        ->assertSet('tableSearch', 'Queue match');
    expect((bool) $destination->instance()->tableFilters['open']['isActive'])->toBeFalse();
})->with([
    'My Tickets' => ['test', 'my', 1],
    'received My Tickets' => ['test2', 'my', 2],
]);

it('toggles the overdue filter and can clear it', function () {
    $this->login();
    $old = overdueCardTicket();
    $waiting = overdueCardTicket(['turn' => Turn::User]);
    $fresh = overdueCardTicket();
    $fresh->ticketActivities()->where('type', ActivityType::Message)->update(['created_at' => now()]);
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('overdue')->getLabel())->toBe('Overdue')
        ->and((bool) $page->instance()->tableFilters['overdue']['isActive'])->toBeFalse();
    $page->assertCanSeeTableRecords([$old, $waiting, $fresh])
        ->filterTable('overdue')->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$waiting, $fresh])
        ->removeTableFilter('overdue')->assertCanSeeTableRecords([$old, $waiting, $fresh]);
});

it('opens the overdue filtered all tab from a dashboard', function () {
    $this->login();
    overdueCardTicket();
    overdueCardTicket(['turn' => Turn::User]);
    $card = Livewire::test(OverdueTicketsWidget::class)->instance()->getStats()[0];
    parse_str(parse_url($card->getUrl(), PHP_URL_QUERY), $parameters);
    Livewire::withQueryParams($parameters)->test(ListTickets::class)
        ->assertSet('activeTab', 'all')
        ->assertSet('tableFilters.overdue.isActive', true)
        ->assertCountTableRecords($card->getValue());
});
