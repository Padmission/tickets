<?php

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * The package's own policy, with a host whose tenant scope hides tenant B's
 * tickets from tenant A, and whose supporter pool is the ticket's tenant's when
 * a ticket is given and the signed-in tenant's otherwise, as the README asks.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();

    Gate::policy(Ticket::class, TicketPolicy::class);

    [$this->aSupporter, $this->bSupporter, $this->bRequester, $this->staff] = User::factory()->count(4)->create();

    TicketPlugin::get('test')->customizeTicketQuery(fn (Builder $query): Builder => $query->where('subject', '!=', 'Tenant B'));
    TicketPlugin::get('test')->allSupportersQuery(fn (?Ticket $ticket = null) => $ticket?->subject === 'Tenant B'
        ? User::query()->whereKey($this->bSupporter->id)
        : User::query()->whereKey($this->aSupporter->id));
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->staff->id));

    $this->escalation = escalationFrom('test', ['subject' => 'Escalation', 'submitter_id' => $this->bSupporter->id]);
    $this->original = Ticket::factory()->open()->create([
        'panel' => 'test',
        'subject' => 'Tenant B',
        'submitter_id' => $this->bRequester->id,
        'linked_ticket_id' => $this->escalation->id,
    ]);
    $this->original->ticketActivities()->create([
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'user_id' => $this->bRequester->id,
        'content' => 'Tenant B secret',
    ]);
});

it('answers 404 to another tenant\'s supporter on an escalated original', function () {
    $this->actingAs($this->aSupporter);

    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->original]))->assertNotFound();

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->original]), ['content' => '<p>From tenant A</p>'])
        ->assertNotFound();

    $this->postJson(route('padmission-tickets::api.mark-seen', ['ticket' => $this->original]), ['last_seen_activity_id' => 1])
        ->assertNotFound();

    expect($this->original->ticketActivities()->where('content', 'like', '%From tenant A%')->exists())->toBeFalse();
});

it('asks the ticket\'s own supporters, not the signed-in tenant\'s, whether someone supports it', function () {
    Filament::setCurrentPanel('test2');

    expect(Gate::forUser($this->aSupporter)->allows('view', $this->original))->toBeFalse()
        ->and(Gate::forUser($this->aSupporter)->allows('manage', $this->original))->toBeFalse()
        ->and(Gate::forUser($this->bSupporter)->allows('manage', $this->original))->toBeTrue();
});
