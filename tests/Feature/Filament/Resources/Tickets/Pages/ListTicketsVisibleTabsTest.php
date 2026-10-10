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

it('shows tabs only to supporters, and the Direct questions filter only where they escalate', function (string $panel, bool $supporter, bool $escalates) {
    TicketPlugin::get('test')->allowLinkedTicketsTo($escalates ? ['test2'] : []);
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    if (! $supporter) {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKeyNot($me->id));
    }

    $page = Livewire::test(ListTickets::class);
    $visible = array_filter($page->instance()->getCachedTabs(), fn ($tab): bool => $tab->isVisible());
    $expected = $supporter ? ['all', 'my'] : [];
    expect(array_keys($page->instance()->getCachedTabs()))->toBe($expected)
        ->and(array_keys($visible))->toBe($expected);

    if ($supporter) {
        expect($visible['all']->getLabel())->toBe('All Tickets')
            ->and($visible['my']->getLabel())->toBe('My Tickets');
    }

    expect($page->instance()->getTable()->getFilter('direct_questions', withHidden: true)->isVisible())->toBe($panel === 'test' && $supporter && $escalates);

    $document = new DOMDocument;
    @$document->loadHTML($page->html());
    expect((new DOMXPath($document))->query('//*[@role="tab"]')->length)->toBe(count($expected));
})->with([
    'organization supporter with escalation' => ['test', true, true],
    'organization supporter without escalation' => ['test', true, false],
    'organization requester with escalation' => ['test', false, true],
    'organization requester without escalation' => ['test', false, false],
    'receiving supporter' => ['test2', true, true],
    'receiving requester' => ['test2', false, true],
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
