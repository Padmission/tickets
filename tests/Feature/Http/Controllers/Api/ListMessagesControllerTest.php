<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertUnauthorized();
});

it('needs to be submitter without permission', function () {
    Gate::before(fn (User $user, string $ability) => $ability === 'manage' ? false : null);

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

it('gives the ticket\'s subject, so a chat opened by the ticket\'s id alone can head it', function () {
    $user = $this->login();
    $ticket = Ticket::factory()->create(['submitter_id' => $user->id, 'subject' => 'Recert rent is wrong for household 14']);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk()
        ->assertJsonPath('ticket.subject', 'Recert rent is wrong for household 14');
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

it('names the people an escalation\'s history mentions through the ticket\'s own panel, whatever panel reads it', function (array $headers) {
    [$owner, $colleague] = User::factory()->count(2)->create();
    $staff = User::factory()->create(['name' => 'Kevin McKee']);
    $stranger = User::factory()->create(['name' => 'Someone Else']);

    TicketPlugin::get('test2')->modifyRelationshipScopes(fn ($relation) => $relation->whereKeyNot($stranger->id)->withoutGlobalScope('acting-tenant'));
    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot([$staff->id, $stranger->id]));

    $this->login();

    $escalation = Ticket::factory()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $colleague->id, 'assignee_id' => $staff->id]);
    Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

    $activity = fn (ActivityType $type, ?int $userId, array $data = []) => TicketActivity::factory()->create([
        'ticket_id' => $escalation->id,
        'type' => $type,
        'sender' => ActivitySender::System,
        'user_id' => $userId,
        'data' => $data,
    ]);

    $activity(ActivityType::AssigneeChanged, $staff->id, ['from' => null, 'to' => $staff->id]);
    $activity(ActivityType::Reopened, $staff->id);
    $activity(ActivityType::HandedOver, $colleague->id, ['from' => $owner->id, 'to' => $colleague->id]);
    $activity(ActivityType::AssigneeChanged, $staff->id, ['from' => $staff->id, 'to' => $stranger->id]);

    $content = collect($this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $escalation]), $headers)
        ->assertOk()
        ->getData()->messages)->pluck('content')->all();

    expect($content)->toBe([
        'Assigned to Kevin McKee',
        'Conversation reopened by Kevin McKee',
        "{$colleague->name} took over this escalation from {$owner->name}",
        "Assigned to user {$stranger->id}",
    ]);
})->with([
    'the escalating team\'s widget' => [['X-Padmission-Tickets-Panel' => 'panel-test']],
    'the receiving team\'s widget' => [['X-Padmission-Tickets-Panel' => 'panel-test2']],
    'no panel named' => [[]],
])->after(fn () => User::clearBootedModels());
