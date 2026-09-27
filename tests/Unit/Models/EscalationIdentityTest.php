<?php

use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

function expectEscalation(Ticket $ticket, bool $expected): void
{
    expect($ticket->isEscalation())->toBe($expected)
        ->and(Ticket::query()->escalations()->whereKey($ticket->id)->exists())->toBe($expected)
        ->and(Ticket::query()->withoutEscalations()->whereKey($ticket->id)->exists())->toBe(! $expected);
}

beforeEach(function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
});

it('is an escalation when a ticket links to it', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']);
    $original = Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

    expectEscalation($escalation, true);
    expectEscalation($original, false);
});

it('counts a linked child hidden by a global scope', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2']);
    Ticket::factory()->create(['linked_ticket_id' => $escalation->id, 'subject' => 'Hidden']);

    Ticket::addGlobalScope('hide', fn ($query) => $query->where('subject', '!=', 'Hidden'));

    try {
        expectEscalation(Ticket::query()->findOrFail($escalation->id), true);
    } finally {
        Ticket::clearBootedModels();
    }
});

it('stays an escalation after all its originals are removed', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']);
    $escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, null, ['original' => 999]);

    expectEscalation($escalation, true);
});

it('is not an escalation when only its panel differs from its source panel', function () {
    $widgetTicket = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']);

    expectEscalation($widgetTicket, false);
    expect($widgetTicket->isEscalationFrom('test'))->toBeFalse();
});

it('is an escalation from the panel of its originals when it has no source panel', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => null]);
    Ticket::factory()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id]);

    expectEscalation($escalation, true);
    expect($escalation->isEscalationFrom('test'))->toBeTrue()
        ->and($escalation->isEscalationFrom('test3'))->toBeFalse();
});

it('is an escalation from its source panel once its originals are removed', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']);
    $escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, null, ['original' => 999]);

    expect($escalation->isEscalationFrom('test'))->toBeTrue();
});

it('is not an escalation from a panel that cannot escalate to its panel', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test3', 'source_panel' => 'test']);
    Ticket::factory()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id]);

    expect($escalation->isEscalation())->toBeTrue()
        ->and($escalation->isEscalationFrom('test'))->toBeFalse();
});

it('is not an escalation before it is saved', function () {
    expect(Ticket::factory()->make()->isEscalation())->toBeFalse();
});

it('answers again after a link is written through the escalation it was asked about', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2']);
    $original = Ticket::factory()->create(['linked_ticket_id' => null]);

    expect($escalation->isEscalation())->toBeFalse();

    resolve(TicketEscalationLinks::class)->linkNewEscalation($original, $escalation);

    expect($escalation->isEscalation())->toBeTrue();
});

it('answers again after a refresh', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2']);

    expect($escalation->isEscalation())->toBeFalse();

    Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

    expect($escalation->isEscalation())->toBeFalse()
        ->and($escalation->refresh()->isEscalation())->toBeTrue();
});

it('finds in SQL exactly the escalations isEscalationFrom() names', function (Closure $scenario, bool $expected) {
    $escalation = $scenario();

    expect($escalation->isEscalationFrom('test'))->toBe($expected)
        ->and(Ticket::query()->escalationsFrom('test')->whereKey($escalation->id)->exists())->toBe($expected);
})->with([
    'by an original in the panel' => [fn () => tap(Ticket::factory()->create(['panel' => 'test2', 'source_panel' => null]), fn (Ticket $escalation) => Ticket::factory()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id])), true],
    'by source, its original in another panel and no history note' => [fn () => tap(Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']), fn (Ticket $escalation) => Ticket::factory()->create(['panel' => 'test3', 'linked_ticket_id' => $escalation->id])), true],
    'by source, its originals all removed' => [fn () => tap(Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']), fn (Ticket $escalation) => $escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System)), true],
    'by a deleted original in the panel' => [fn () => tap(Ticket::factory()->create(['panel' => 'test2', 'source_panel' => null]), fn (Ticket $escalation) => Ticket::factory()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id])->delete()), true],
    'a widget ticket filed from the panel' => [fn () => Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']), false],
    'from another panel' => [fn () => tap(Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test3']), fn (Ticket $escalation) => Ticket::factory()->create(['panel' => 'test3', 'linked_ticket_id' => $escalation->id])), false],
    'in a panel the panel cannot escalate to' => [fn () => tap(Ticket::factory()->create(['panel' => 'test3', 'source_panel' => 'test']), fn (Ticket $escalation) => Ticket::factory()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id])), false],
]);

it('takes a list row\'s escalation identity from its conversation state', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test']);
    Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);
    $plain = Ticket::factory()->create();

    $rows = Ticket::query()->withConversationState()->whereKey([$escalation->id, $plain->id])->get()->keyBy('id');

    DB::enableQueryLog();

    expect($rows[$escalation->id]->isEscalation())->toBeTrue()
        ->and($rows[$plain->id]->isEscalation())->toBeFalse()
        ->and(DB::getQueryLog())->toBeEmpty();
});
