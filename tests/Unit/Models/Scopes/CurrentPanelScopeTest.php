<?php

use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\TicketStatus;

it('filters by panel', function () {
    [$statusA, $statusB] = TicketStatus::factory()
        ->sequence(
            ['panel' => 'test'],
            ['panel' => 'test2'],
        )
        ->count(2)
        ->create();

    $statuses = TicketStatus::query()->tap(new CurrentPanelScope)->get();

    expect($statuses->count())->toEqual(1)
        ->and($statuses->first()->id)->toEqual($statusA->id);
});
