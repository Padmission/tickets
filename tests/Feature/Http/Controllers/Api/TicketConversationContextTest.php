<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketConversationContext;

function contextServiceReturning(array $sections): object
{
    $service = new class extends TicketConversationContext
    {
        public array $sections = [];

        public int $asked = 0;

        public function sectionsFor(Ticket $ticket, Model $viewer): array
        {
            $this->asked++;

            return $this->sections;
        }
    };

    $service->sections = $sections;
    app()->instance(TicketConversationContext::class, $service);

    return $service;
}

it('has no context by default', function () {
    $user = $this->login();
    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket, 'context' => 1]))
        ->assertOk()
        ->assertJsonPath('context', []);
});

it('gives the sections the context service supplies when asked on the first load', function () {
    $user = $this->login();
    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);
    contextServiceReturning([['heading' => 'Earlier chat', 'html' => '<p>Hello</p>']]);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket, 'context' => 1]))
        ->assertOk()
        ->assertJsonPath('context.0.heading', 'Earlier chat')
        ->assertJsonPath('context.0.html', '<p>Hello</p>');
});

it('does not ask for context on a poll', function () {
    $user = $this->login();
    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);
    $service = contextServiceReturning([['heading' => 'Earlier chat', 'html' => '<p>Hello</p>']]);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk()
        ->assertJsonMissingPath('context');

    expect($service->asked)->toBe(0);
});

it('does not ask for context for someone who may not read the ticket', function () {
    Gate::before(fn ($user, string $ability) => $ability === 'manage' ? false : null);

    $this->login();
    $ticket = Ticket::factory()->create();
    $service = contextServiceReturning([['heading' => 'Earlier chat', 'html' => '<p>Hello</p>']]);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket, 'context' => 1]))
        ->assertForbidden();

    expect($service->asked)->toBe(0);
});
