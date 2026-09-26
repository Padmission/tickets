<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertUnauthorized();
});

it('needs to be submitter without permission', function () {
    Gate::policy(Ticket::class, get_class(new class
    {
        public function view(User $user, Ticket $ticket): bool
        {
            return false;
        }

        public function manage(User $user, Ticket $ticket): bool
        {
            return false;
        }
    }));

    [$userA, $userB] = User::factory()->count(2)->create();

    $ticketA = Ticket::factory()->create(['submitter_id' => $userA->id]);
    $ticketB = Ticket::factory()->create(['submitter_id' => $userB->id]);

    $this->actingAs($userA);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticketB]))
        ->assertForbidden();

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticketA]))
        ->assertOk();
});

it('lists messages', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()
        ->has(TicketActivity::factory()->count(2))
        ->create(['submitter_id' => $user->id]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk();

    $json = $resp->getData();

    expect($json)
        ->toHaveKeys(['ticket', 'messages'])
        ->and($json->messages)
        ->toHaveCount(2)
        ->{0}->toHaveKeys(['side', 'user_name', 'content', 'attachments', 'created_at']);
});

it('filters some messages without elevated rights', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()
        ->has(
            TicketActivity::factory()
                ->sequence(
                    ['type' => ActivityType::Opened],
                    ['type' => ActivityType::TurnChanged],
                )
                ->count(2)
        )
        ->create(['submitter_id' => $user->id]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk();

    $json = $resp->getData();

    expect($json->messages)->toHaveCount(1);
});

it('lists messages with offset', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()
        ->has(TicketActivity::factory()->count(2))
        ->create(['submitter_id' => $user->id]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', [
            'ticket' => $ticket,
            'offset' => 1,
        ]))
        ->assertOk();

    $json = $resp->getData();

    expect($json->messages)->toHaveCount(1);
});

it('returns messages in ascending chronological order', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

    $activities = TicketActivity::factory()
        ->count(3)
        ->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
        ]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk();

    $ids = collect($resp->getData()->messages)->pluck('id')->all();

    expect($ids)->toBe($activities->pluck('id')->sort()->values()->all());
});

it('returns only messages after the offset cursor in ascending order', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

    [$first, $second, $third] = TicketActivity::factory()
        ->count(3)
        ->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
        ]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', [
            'ticket' => $ticket,
            'offset' => $first->id,
        ]))
        ->assertOk();

    $ids = collect($resp->getData()->messages)->pluck('id')->all();

    expect($ids)->toBe([$second->id, $third->id]);
});

it('forbids listing messages when the user cannot view the ticket even when manage passes', function () {
    Gate::before(fn (User $authUser, string $ability) => $ability === 'view' ? false : null);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertForbidden();
});
