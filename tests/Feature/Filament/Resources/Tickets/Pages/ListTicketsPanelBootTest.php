<?php

use Filament\Exceptions\NoDefaultPanelSetException;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OverdueTicketsWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketUrlService;
use Padmission\Tickets\Tests\Fixtures\CustomListTickets;
use Padmission\Tickets\Tests\Fixtures\OtherListTickets;
use Padmission\Tickets\Tests\Fixtures\RegistersTicketPanelsWithoutDefault;

uses(RegistersTicketPanelsWithoutDefault::class);

it('boots the host routes and registers each panels list without a default or current panel', function () {
    expect(Filament::getCurrentPanel())->toBeNull()
        ->and(fn () => Filament::getDefaultPanel())->toThrow(NoDefaultPanelSetException::class);

    foreach (['organization' => CustomListTickets::class, 'platform' => OtherListTickets::class] as $id => $page) {
        $url = TicketResource::getUrl('index', panel: $id);
        $route = app('router')->getRoutes()->match(Request::create($url));

        expect($route->getActionName())->toBe($page)
            ->and($route->getName())->toBe("filament.{$id}.resources.tickets.index")
            ->and(TicketResource::getPages(Filament::getPanel($id))['index']->getPage())->toBe($page);
    }

    expect(TicketResource::getPages()['index']->getPage())->toBe(ListTickets::class)
        ->and(invade(new OverdueTicketsWidget)->getTablePage())->toBe(ListTickets::class)
        ->and(Filament::getCurrentPanel())->toBeNull();
});

it('registers a panels routes independently of a different current panel', function () {
    Filament::setCurrentPanel('platform');
    $panel = Filament::getPanel('organization');

    Route::name('probe.')->prefix('probe')->group(
        fn () => TicketResource::registerRoutes($panel),
    );
    $route = app('router')->getRoutes()->match(Request::create(url('/probe/tickets')));

    expect($route->getActionName())->toBe(CustomListTickets::class)
        ->and(Filament::getCurrentPanel())->toBe(Filament::getPanel('platform'));
});

it('generates resource and custom page URLs with explicit panels in console and jobs', function () {
    expect(Filament::getCurrentPanel())->toBeNull();

    foreach (['organization' => CustomListTickets::class, 'platform' => OtherListTickets::class] as $id => $page) {
        expect($page::getUrl(panel: $id))->toBe(url("/{$id}/tickets"))
            ->and(TicketResource::getUrl(panel: $id))->toBe(url("/{$id}/tickets"))
            ->and(ViewTicket::getUrl(['record' => 42], panel: $id))->toBe(url("/{$id}/tickets/42/view"))
            ->and((new TicketUrlService)->originalUrl((new Ticket)->forceFill(['id' => 42, 'panel' => $id])))
            ->toBe(url("/{$id}/tickets/42/view"));
    }

    expect(Filament::getCurrentPanel())->toBeNull();
});
