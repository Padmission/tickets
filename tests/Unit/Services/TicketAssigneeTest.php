<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketAssignee;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * 'acting-tenant' stands in for a host's tenant scope: the current panel may
 * not see the person, and only the ticket's own panel lifts it.
 */
beforeEach(function () {
    $this->login();
    $this->person = User::factory()->create(['name' => 'Kevin McKee']);

    TicketPlugin::get('test2')->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('acting-tenant'));
    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($this->person->id));
});

afterEach(fn () => User::clearBootedModels());

it('finds the assignee of another panel\'s ticket through that panel\'s scopes', function () {
    $ticket = Ticket::factory()->create(['panel' => 'test2', 'assignee_id' => $this->person->id]);

    expect($ticket->assignee)->toBeNull()
        ->and(TicketAssignee::for($ticket)?->getKey())->toBe($this->person->id);
});

it('keeps the current panel\'s scopes for its own tickets', function () {
    $ticket = Ticket::factory()->create(['panel' => 'test', 'assignee_id' => $this->person->id]);

    expect(TicketAssignee::for($ticket))->toBeNull();
});

it('finds nobody for an unassigned ticket, or where no panel lifts the scope', function () {
    $unassigned = Ticket::factory()->create(['panel' => 'test2', 'assignee_id' => null]);
    $elsewhere = Ticket::factory()->create(['panel' => 'test3', 'assignee_id' => $this->person->id]);

    expect(TicketAssignee::for($unassigned))->toBeNull()
        ->and(TicketAssignee::for($elsewhere))->toBeNull();
});

it('eager loads assignees through the listed panels\' scopes', function () {
    $ticket = Ticket::factory()->create(['panel' => 'test2', 'assignee_id' => $this->person->id]);

    $loaded = TicketAssignee::eagerLoadForForeignPanels(Ticket::query(), ['test2'])->whereKey($ticket->id)->sole();
    $plain = TicketAssignee::eagerLoadForForeignPanels(Ticket::query(), ['test3'])->whereKey($ticket->id)->sole();

    expect($loaded->relationLoaded('assignee'))->toBeTrue()
        ->and($loaded->assignee?->getKey())->toBe($this->person->id)
        ->and($plain->relationLoaded('assignee'))->toBeFalse()
        ->and($plain->assignee)->toBeNull();
});
