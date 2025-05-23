<?php

use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;

it('filters by panel', function () {
    [$ticketA, $ticketB] = Ticket::factory()
        ->sequence(
            ['panel' => 'test'],
            ['panel' => 'test2'],
        )
        ->count(2)
        ->create();

    $tickets = Ticket::query()->tap(new CurrentPanelScope)->get();

    expect($tickets->count())->toEqual(1)
        ->and($tickets->first()->id)->toEqual($ticketA->id);
});
