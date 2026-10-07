<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
});

it('shows only All Tickets and My Tickets on both sides for supporters and requesters', function (string $panel, bool $supporter) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    if (! $supporter) {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKeyNot($me->id));
    }

    $page = Livewire::test(ListTickets::class);
    $visible = array_filter($page->instance()->getCachedTabs(), fn ($tab): bool => $tab->isVisible());
    expect(array_keys($visible))->toBe(['all', 'my'])
        ->and($visible['all']->getLabel())->toBe('All Tickets')
        ->and($visible['my']->getLabel())->toBe('My Tickets');

    $document = new DOMDocument;
    @$document->loadHTML($page->html());
    expect((new DOMXPath($document))->query('//*[@role="tab"]')->length)->toBe(2);
})->with([
    'organization supporter' => ['test', true],
    'organization requester' => ['test', false],
    'receiving supporter' => ['test2', true],
    'receiving requester' => ['test2', false],
]);

it('keeps closed history available in both visible tabs when the open filter is cleared', function (string $panel) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    $closed = Ticket::factory()->closed()->create(['panel' => $panel, 'assignee_id' => $me->id]);

    foreach (['all', 'my'] as $tab) {
        Livewire::test(ListTickets::class)->set('activeTab', $tab)
            ->assertCanNotSeeTableRecords([$closed])
            ->removeTableFilter('open')->assertCanSeeTableRecords([$closed]);
    }
})->with(['test', 'test2']);
