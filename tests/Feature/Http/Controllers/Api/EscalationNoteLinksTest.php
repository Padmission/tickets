<?php

use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

it('links escalation notes for the panel the chat widget is in', function (string $panel, bool $linked) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $escalation = Ticket::factory()->create(['panel' => 'test2']);
    $ticket = Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);
    $ticket->addTicketActivity(ActivityType::AddedToEscalation, ActivitySender::System, $user->id, ['escalation' => $escalation->id]);

    $content = collect($this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]), ['X-Padmission-Tickets-Panel' => $panel])
        ->assertOk()
        ->json('messages'))
        ->pluck('content')
        ->first(fn (?string $content): bool => str_contains((string) $content, 'Added to'));

    expect(str_contains($content, '<a href="'))->toBe($linked)
        ->and(strip_tags($content))->toStartWith('Added to the escalation by');
})->with([
    'panel it can be opened in' => ['panel-test', true],
    'unknown panel' => ['panel-missing', false],
]);

it('links an original, in the escalation\'s own panel, to the escalation with the original beside it', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $escalation = Ticket::factory()->create(['panel' => 'test']);
    $original = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);
    $escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, $user->id, ['original' => $original->id]);

    $content = collect($this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $escalation]), ['X-Padmission-Tickets-Panel' => 'panel-test'])
        ->assertOk()
        ->json('messages'))
        ->pluck('content')
        ->first(fn (?string $content): bool => str_contains((string) $content, 'added to'));

    expect($content)->toContain('href="'.e(TicketResource::getUrl('view', ['record' => $escalation, 'linked' => $original->id], panel: 'test')).'"')
        ->and($content)->not->toContain(TicketResource::getUrl('view', ['record' => $original], panel: 'test').'"');
});

it('links an original to its own page from another panel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $escalation = Ticket::factory()->create(['panel' => 'test2']);
    $original = Ticket::factory()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id]);
    $escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, $user->id, ['original' => $original->id]);

    $content = collect($this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $escalation]), ['X-Padmission-Tickets-Panel' => 'panel-test'])
        ->assertOk()
        ->json('messages'))
        ->pluck('content')
        ->first(fn (?string $content): bool => str_contains((string) $content, 'added to'));

    expect($content)->toContain('href="'.e(TicketResource::getUrl('view', ['record' => $original], panel: 'test')).'"');
});
