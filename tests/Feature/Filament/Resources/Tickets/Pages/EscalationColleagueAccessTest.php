<?php

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->owner = User::factory()->create(['name' => 'Test Admin']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->padmission = User::factory()->create(['name' => 'Kevin McKee']);
    $this->stranger = User::factory()->create(['name' => 'Sam Rivers']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->owner->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Padmission');
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->padmission->id));

    $this->escalation = Ticket::factory()->open()->create([
        'panel' => 'test2',
        'source_panel' => 'test',
        'submitter_id' => $this->owner->id,
        'assignee_id' => $this->padmission->id,
        'turn' => Turn::User,
    ]);
    $this->original = Ticket::factory()->open()->create([
        'linked_ticket_id' => $this->escalation->id,
        'submitter_id' => $this->requester->id,
        'assignee_id' => $this->owner->id,
        'turn' => Turn::Supporter,
    ]);

    $this->escalation->ticketActivities()->create([
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
        'user_id' => $this->padmission->id,
        'content' => 'It is fixed.',
    ]);
});

it('opens the escalation to a colleague of the team that sent it, with Padmission\'s reply', function () {
    $this->login($this->colleague);

    $this->get(TicketResource::getUrl('view', ['record' => $this->escalation]))->assertOk();

    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->escalation]))
        ->assertOk()
        ->assertJsonPath('messages.0.content', 'It is fixed.');
});

it('opens a closed escalation to a colleague', function () {
    $this->escalation->close(closedById: $this->padmission->id);
    $this->login($this->colleague);

    $this->get(TicketResource::getUrl('view', ['record' => $this->escalation]))->assertOk();
});

it('keeps the escalation open to its owner', function () {
    $this->login($this->owner);

    $this->get(TicketResource::getUrl('view', ['record' => $this->escalation]))->assertOk();
});

it('refuses the escalation to someone outside the team that sent it', function (string $who) {
    $this->login($this->{$who});

    $this->get(TicketResource::getUrl('view', ['record' => $this->escalation]))->assertForbidden();
})->with([
    'a requester of an original' => ['requester'],
    'someone on neither team' => ['stranger'],
]);

it('lets a colleague read the escalation but not reply until they take it over', function () {
    $this->login($this->colleague);
    $auth = resolve(TicketAuth::class);

    expect(Gate::allows('view', $this->escalation))->toBeTrue()
        ->and(Gate::allows('manage', $this->escalation))->toBeFalse()
        ->and($auth->canReply($this->escalation, $this->colleague))->toBeFalse();

    $this->escalation->update(['submitter_id' => $this->colleague->id]);

    expect($auth->canReply($this->escalation->refresh(), $this->colleague))->toBeTrue();
});

it('shows the colleague the link to the escalation on the original', function () {
    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertSee('Open the escalation');
});
