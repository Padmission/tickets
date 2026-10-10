<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OverdueTicketsWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\Fixtures\CustomListTickets;
use Padmission\Tickets\TicketPlugin;

it('defaults the index page to the package list', function () {
    expect(TicketPlugin::make()->getListPage())->toBe(ListTickets::class)
        ->and(TicketResource::getPages()['index']->getPage())->toBe(ListTickets::class);
});

it('substitutes the index page only on the configured panel', function () {
    $plugin = TicketPlugin::get();

    expect($plugin->listPage(CustomListTickets::class))->toBe($plugin)
        ->and($plugin->getListPage())->toBe(CustomListTickets::class)
        ->and(TicketResource::getPages()['index']->getPage())->toBe(CustomListTickets::class)
        ->and(TicketResource::getPages()['view']->getPage())->toBe(ViewTicket::class);

    Filament::setCurrentPanel('test2');

    expect(TicketPlugin::get()->getListPage())->toBe(ListTickets::class)
        ->and(TicketResource::getPages()['index']->getPage())->toBe(ListTickets::class);

    Filament::setCurrentPanel('test');
    $plugin->listPage(ListTickets::class);

    expect(TicketResource::getPages()['index']->getPage())->toBe(ListTickets::class);
});

it('rejects classes that do not extend the ticket list', function (string $page) {
    $plugin = TicketPlugin::make();

    expect(fn () => $plugin->listPage($page))->toThrow(InvalidArgumentException::class)
        ->and($plugin->getListPage())->toBe(ListTickets::class);
})->with([ViewTicket::class, stdClass::class, 'MissingListTickets']);

it('registers routes with the target panels list page even when another panel is current', function () {
    TicketPlugin::get('test2')->listPage(CustomListTickets::class);
    $pages = TicketResource::getPages();

    expect($pages['index']->registerRoute(Filament::getPanel('test2'))->getActionName())
        ->toBe(CustomListTickets::class);

    Filament::setCurrentPanel('test2');
    $pages = TicketResource::getPages();

    expect($pages['index']->registerRoute(Filament::getPanel('test'))->getActionName())
        ->toBe(ListTickets::class);
});

it('counts the overdue card on the package list query, and leaves the custom list query to the page and its filtered destination', function () {
    (new TicketStatusSeeder)->run();
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(14, 0));
    $me = $this->login();
    TicketPlugin::get()->listPage(CustomListTickets::class);

    $tickets = Ticket::factory()->open()->sequence(
        ['subject' => 'Custom list'],
        ['subject' => 'Other list'],
    )->count(2)->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);

    foreach ($tickets as $ticket) {
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'sender' => ActivitySender::User,
            'created_at' => '2026-10-02 13:59:59',
        ]);
    }

    $page = Livewire::test(CustomListTickets::class)
        ->assertSet('activeTab', 'my')
        ->assertCanSeeTableRecords([$tickets[0]])
        ->assertCanNotSeeTableRecords([$tickets[1]]);
    $card = Livewire::test(OverdueTicketsWidget::class, $page->instance()->getWidgetData())->instance()->getStats()[0];

    expect($card->getValue())->toBe(2);

    parse_str(parse_url($card->getUrl(), PHP_URL_QUERY), $parameters);
    Livewire::withQueryParams($parameters)->test(TicketResource::getPages()['index']->getPage())
        ->assertSet('activeTab', 'my')
        ->assertSet('tableFilters.overdue.isActive', true)
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$tickets[0]])
        ->assertCanNotSeeTableRecords([$tickets[1]]);
});
