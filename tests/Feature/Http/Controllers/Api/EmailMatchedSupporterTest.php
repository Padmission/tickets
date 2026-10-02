<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * A person with several accounts is matched to the supporter pool by email,
 * so the account they are signed in with need not be the one the pool keeps.
 * The chat's API runs outside any panel, where the policy asks the pool itself.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();

    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->pooled = User::factory()->create(['email' => 'kevin@example.com']);
    $this->signedIn = User::factory()->create(['email' => 'Kevin@Example.com']);

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($this->pooled->id))->matchSupportersBy('email');

    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => User::factory()->create()->id]);

    Filament::setCurrentPanel(null);
    $this->actingAs($this->signedIn);
});

it('lets a supporter matched by email read and reply in the chat', function () {
    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))->assertOk();

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])->assertOk();

    expect($this->ticket->ticketActivities()->where('type', ActivityType::Message)->where('user_id', $this->signedIn->id)->exists())->toBeTrue();
});

it('still refuses someone whose email is not in the pool', function () {
    $this->actingAs(User::factory()->create(['email' => 'someone@example.com']));

    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))->assertForbidden();
});
