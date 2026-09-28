<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Http\DataMappers\TicketStatusMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicket;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $this
        ->getJson(route('padmission-tickets::api.index'))
        ->assertUnauthorized();
});

it('requires create permission', function () {
    Gate::before(fn (User $user, string $ability) => $ability === 'create' ? false : null);

    $user = User::factory()->create();

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.index'))
        ->assertForbidden();
});

it('lists users tickets', function () {
    $this->freezeTime();

    [$userA, $userB] = User::factory()->count(2)->create();

    (new TicketStatusSeeder)->run();

    $ticketA = CustomTicket::factory()->open()->create(['submitter_id' => $userA->id]);
    CustomTicket::factory()->open()->create(['submitter_id' => $userB->id]);

    $this->actingAs($userA);

    $resp = $this
        ->getJson(route('padmission-tickets::api.index'))
        ->assertStatus(200);

    expect($resp->json('tickets'))
        ->toHaveCount(1)
        ->{0}->toEqual([
            'id' => $ticketA->id,
            'subject' => $ticketA->subject,
            'status' => TicketStatusMapper::map($ticketA->status),
            'latest_message' => null,
            'is_closed' => false,
            'needs_attention' => true,
            'is_unread' => false,
            'updated_at' => now()->diffForHumans(),
        ]);
});

it('leaves out escalations the user opened but keeps widget tickets filed into another panel', function () {
    $user = User::factory()->create();

    (new TicketStatusSeeder)->run();

    $widgetTicket = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $user->id]);
    $escalation = escalationFrom(attributes: ['submitter_id' => $user->id]);
    Ticket::factory()->open()->create(['panel' => 'test', 'linked_ticket_id' => $escalation->id]);

    $this->actingAs($user);

    $resp = $this
        ->getJson(route('padmission-tickets::api.index'))
        ->assertOk();

    expect(collect($resp->json('tickets'))->pluck('id')->all())->toBe([$widgetTicket->id]);
});
