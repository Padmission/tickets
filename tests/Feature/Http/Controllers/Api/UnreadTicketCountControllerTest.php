<?php

use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\Tests\User;

it('requires login', function () {
    $this
        ->getJson(route('padmission-tickets::api.unread-count'))
        ->assertUnauthorized();
});

it('counts a ticket with an unseen supporter response', function () {
    $user = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.unread-count'))
        ->assertOk()
        ->assertJson(['unread_count' => 1]);
});

it('does not count tickets with only the submitters own messages', function () {
    $user = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
    ]);

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.unread-count'))
        ->assertOk()
        ->assertJson(['unread_count' => 0]);
});

it('stays read when a hidden turn changed activity follows the seen supporter response', function () {
    $user = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    $supporterMessage = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::TurnChanged,
        'sender' => ActivitySender::System,
    ]);

    TicketUserState::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'last_seen_activity_id' => $supporterMessage->id,
    ]);

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.unread-count'))
        ->assertOk()
        ->assertJson(['unread_count' => 0]);
});

it('does not count closed tickets with unseen supporter responses', function () {
    $user = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->closed()->create(['submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.unread-count'))
        ->assertOk()
        ->assertJson(['unread_count' => 0]);
});

it('does not count an escalation the user opened', function () {
    $user = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $escalation = escalationFrom(attributes: ['submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $escalation->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.unread-count'))
        ->assertOk()
        ->assertJson(['unread_count' => 0]);
});

it('counts a widget ticket filed into another panel', function () {
    $user = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $ticket = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $user->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.unread-count'))
        ->assertOk()
        ->assertJson(['unread_count' => 1]);
});
