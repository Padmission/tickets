<?php

use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
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
