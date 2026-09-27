<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    Gate::policy(Ticket::class, TicketPolicy::class);
});

it('lets the submitter view their own ticket', function () {
    $submitter = User::factory()->create();

    $ticket = Ticket::factory()->create(['submitter_id' => $submitter->id]);

    $this->actingAs($submitter);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk();
});

it('forbids a non-supporter from viewing another user\'s ticket', function () {
    [$submitter, $supporter, $outsider] = User::factory()->count(3)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $ticket = Ticket::factory()->create(['submitter_id' => $submitter->id]);

    $this->actingAs($outsider);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertForbidden();
});

it('lets a genuine supporter view another user\'s ticket', function () {
    [$submitter, $supporter] = User::factory()->count(2)->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    $ticket = Ticket::factory()->create(['submitter_id' => $submitter->id]);

    $this->actingAs($supporter);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk();
});

it('denies non-submitters when the supporters set is empty', function () {
    [$submitter, $outsider] = User::factory()->count(2)->create();

    $this->modifyPlugin(function ($plugin) {
        $plugin->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));
    });

    $ticket = Ticket::factory()->create(['submitter_id' => $submitter->id]);

    $this->actingAs($outsider);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertForbidden();
});

it('resolves supporter status per ticket panel', function () {
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->modifyPlugin(function ($plugin) use ($supporter) {
        $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    });

    expect(Gate::forUser($supporter)->allows('manage', $ticket))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('manage', $ticket))->toBeFalse();
});

it('knows the submitter whatever type their id was read back as', function () {
    [$submitter, $supporter] = User::factory()->count(2)->create();

    $this->modifyPlugin(fn ($plugin) => $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id)));

    $ticket = Ticket::factory()->create(['submitter_id' => $submitter->id]);
    $ticket->submitter_id = (string) $submitter->id;

    expect(Gate::forUser($submitter)->allows('view', $ticket))->toBeTrue()
        ->and(Gate::forUser($submitter)->allows('manage', $ticket))->toBeFalse()
        ->and($ticket->isSubmittedBy($submitter->id))->toBeTrue()
        ->and($ticket->isSubmittedBy((string) $submitter->id))->toBeTrue()
        ->and($ticket->isSubmittedBy($supporter))->toBeFalse()
        ->and($ticket->isSubmittedBy(null))->toBeFalse()
        ->and(Ticket::factory()->make(['submitter_id' => null])->isSubmittedBy(null))->toBeFalse();
});

it('finds no supporters for a ticket whose panel a queue worker never registered', function () {
    $ticket = Ticket::factory()->create(['panel' => 'retired-panel']);

    expect(Gate::forUser(User::factory()->create())->allows('view', $ticket))->toBeFalse();
});

it('uses the page\'s resolved pool for the signed-in supporter, matched by email', function () {
    Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['email']));
    $pooled = User::factory()->create(['email' => 'kevin@example.com']);
    $otherAccount = User::factory()->create(['email' => 'kevin@example.com']);

    $this->modifyPlugin(fn ($plugin) => $plugin
        ->allSupportersQuery(fn () => User::query()->whereKey($pooled->id))
        ->matchSupportersBy('email'));

    $ticket = Ticket::factory()->create(['submitter_id' => User::factory()->create()->id]);

    $this->actingAs($otherAccount);

    expect(Gate::allows('view', $ticket))->toBeTrue();
});
