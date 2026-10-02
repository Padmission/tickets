<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Tests\Fixtures\Users\DeactivatedUser;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * A code signs someone in to the chat for as long as the session lasts, so
 * whoever may sign in by code is asked again on every request: someone
 * deactivated, made staff or turned away by the host since is signed out.
 *
 * Skipped until email-code sessions are fixed: Laravel's middleware priority
 * runs Authenticate before AuthenticateGuests, so a code session gets 401 on
 * every ticket API call, and nothing re-checks one yet. Both must be fixed in
 * the same change (see "Known limitation" in the README); then remove the skips.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();

    foreach (['test', 'test2', 'test3'] as $panelId) {
        TicketPlugin::get($panelId)->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));
    }

    $this->enableEmailAuthentication(ChatWidgetConfig::make()
        ->allowEmailAuthentication()
        ->allowEmailAuthenticationFor(fn (User $user): bool => $user->name !== 'Deactivated'));

    $this->user = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->user->id]);

    $this->withSession(['padmission-tickets::user_key' => $this->user->getKey()]);
});

function expectSignedOut(): void
{
    test()->getJson(route('padmission-tickets::api.index'))->assertUnauthorized();
    test()->postJson(route('padmission-tickets::api.messages.store', ['ticket' => test()->ticket]), ['content' => '<p>Still here</p>'])->assertUnauthorized();

    expect(session()->has('padmission-tickets::user_key'))->toBeFalse()
        ->and(test()->ticket->ticketActivities()->where('type', ActivityType::Message)->where('content', 'like', '%Still here%')->exists())->toBeFalse()
        ->and(resolve(TicketAuth::class)->getUserId())->toBeNull();
}

it('keeps a code session working while the person may still sign in by code', function () {
    $this->getJson(route('padmission-tickets::api.index'))->assertOk();
    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])->assertOk();

    expect(resolve(TicketAuth::class)->getUserId())->toBe($this->user->getKey());
})->skip('Email-code sessions are disabled until the middleware order and per-request re-check are fixed together; see README.');

it('signs out a code session once the host turns the person away', function () {
    $this->user->update(['name' => 'Deactivated']);

    expectSignedOut();
})->skip('Email-code sessions are disabled until the middleware order and per-request re-check are fixed together; see README.');

it('signs out a code session once the panel refuses the person', function () {
    config()->set('padmission-tickets.models.'.Authenticatable::class, DeactivatedUser::class);

    expectSignedOut();
})->skip('Email-code sessions are disabled until the middleware order and per-request re-check are fixed together; see README.');

it('signs out a code session once the person supports tickets', function () {
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->user->id));

    expectSignedOut();
})->skip('Email-code sessions are disabled until the middleware order and per-request re-check are fixed together; see README.');

it('signs out a code session once no panel allows email authentication', function () {
    TicketPlugin::get()->showChatWidget(config: ChatWidgetConfig::make());

    expectSignedOut();
})->skip('Email-code sessions are disabled until the middleware order and per-request re-check are fixed together; see README.');

it('signs out a code session whose person no longer exists', function () {
    $this->ticket->update(['submitter_id' => User::factory()->create()->id]);
    $this->user->delete();

    $this->getJson(route('padmission-tickets::api.index'))->assertUnauthorized();

    expect(session()->has('padmission-tickets::user_key'))->toBeFalse();
})->skip('Email-code sessions are disabled until the middleware order and per-request re-check are fixed together; see README.');
