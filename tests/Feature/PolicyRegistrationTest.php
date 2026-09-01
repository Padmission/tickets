<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\TicketPluginServiceProvider;

class HostTicket extends Ticket {}

class HostTicketPolicy extends TicketPolicy {}

/**
 * The package binds its policies while registering, so re-running that step picks up
 * whatever `padmission-tickets.policies` holds at the time of the call.
 */
beforeEach(function () {
    $this->registerPolicies = fn () => (new TicketPluginServiceProvider($this->app))->packageRegistered();
});

it('resolves the package policy when none is configured', function () {
    expect(TicketPlugin::resolvePolicyClass(TicketPolicy::class))->toBe(TicketPolicy::class);
});

it('resolves the configured policy', function () {
    config()->set('padmission-tickets.policies.'.TicketPolicy::class, HostTicketPolicy::class);

    expect(TicketPlugin::resolvePolicyClass(TicketPolicy::class))->toBe(HostTicketPolicy::class);
});

it('binds the package policy to the ticket model by default', function () {
    ($this->registerPolicies)();

    expect(Gate::getPolicyFor(Ticket::class))->toBeInstanceOf(TicketPolicy::class);
});

it('binds the configured policy to the ticket model', function () {
    config()->set('padmission-tickets.policies.'.TicketPolicy::class, HostTicketPolicy::class);

    ($this->registerPolicies)();

    expect(Gate::getPolicyFor(Ticket::class))->toBeInstanceOf(HostTicketPolicy::class);
});

it('binds the configured policy to a swapped ticket model', function () {
    config()->set('padmission-tickets.models.'.Ticket::class, HostTicket::class);
    config()->set('padmission-tickets.policies.'.TicketPolicy::class, HostTicketPolicy::class);

    ($this->registerPolicies)();

    expect(Gate::getPolicyFor(HostTicket::class))->toBeInstanceOf(HostTicketPolicy::class);
});
