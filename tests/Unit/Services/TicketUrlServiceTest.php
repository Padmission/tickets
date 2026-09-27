<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\TicketUrlService;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->service = new TicketUrlService;
});

test('can generate action URL with valid ticket URL', function () {
    $ticket = Ticket::factory()->create([
        'data' => [
            'url' => 'https://example.com/tickets',
        ],
    ]);

    $actionUrl = $this->service->getActionUrl($ticket);

    expect($actionUrl)->toBe("https://example.com/tickets#ticket-{$ticket->id}");
});

test('uses app URL when no ticket URL provided', function () {
    $ticket = Ticket::factory()->create([
        'data' => [],
    ]);

    // When no URL is provided, it uses url('/') which should be the current app URL
    $expectedUrl = url('/');

    $actionUrl = $this->service->getActionUrl($ticket);

    expect($actionUrl)->toBe("{$expectedUrl}#ticket-{$ticket->id}");
});

describe('work page', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        Gate::policy(Ticket::class, TicketPolicy::class);

        $this->owner = User::factory()->create();
        $this->colleague = User::factory()->create();
        $this->requester = User::factory()->create();
        $this->padmission = User::factory()->create();

        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->owner->id, $this->colleague->id]));
        TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->padmission->id));

        $this->escalation = escalationFrom(attributes: ['submitter_id' => $this->owner->id]);
        $this->closedOriginal = Ticket::factory()->closed()->create(['linked_ticket_id' => $this->escalation->id, 'submitter_id' => $this->requester->id]);
        $this->openOriginal = Ticket::factory()->open()->create(['linked_ticket_id' => $this->escalation->id, 'submitter_id' => $this->requester->id]);
    });

    it('leaves a requester on the link of the page they filed from', function () {
        $url = $this->service->workPageUrl($this->openOriginal, $this->requester);

        expect($url)->toBeNull()
            ->and($this->service->getActionUrlFor($this->openOriginal, $this->requester))->toBe($this->service->getActionUrl($this->openOriginal));
    });

    it('opens an escalation for its owner in the panel that sent it, beside its first open original', function () {
        expect($this->service->workPageUrl($this->escalation, $this->owner))
            ->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->openOriginal->id}"));
    });

    it('falls back to the first original once none is open', function () {
        $this->openOriginal->close(closedById: $this->owner->id);

        expect($this->service->workPageUrl($this->escalation, $this->owner))
            ->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->closedOriginal->id}"));
    });

    it('uses the panel of the originals when the escalation has no source panel', function () {
        $this->escalation->update(['source_panel' => null]);

        expect($this->service->workPageUrl($this->escalation, $this->owner))
            ->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->openOriginal->id}"));
    });

    it('opens a ticket in its own panel for the people who answer it', function () {
        expect($this->service->workPageUrl($this->escalation, $this->padmission))->toBe(url("/test2/tickets/{$this->escalation->id}/view"))
            ->and($this->service->workPageUrl($this->openOriginal, $this->owner))->toBe(url("/test/tickets/{$this->openOriginal->id}/view"));
    });

    it('never links an escalation to someone who cannot open it', function () {
        expect($this->service->workPageUrl($this->escalation, $this->colleague))
            ->toBe(url("/test/tickets/{$this->openOriginal->id}/view"));

        $this->openOriginal->close(closedById: $this->owner->id);

        expect($this->service->workPageUrl($this->escalation, $this->colleague))
            ->toBe(url('/test/tickets?tab=linked'));
    });

    it('links an escalation with no source panel or originals from the panel that escalates to its panel', function () {
        $escalation = escalationFrom(attributes: ['submitter_id' => $this->owner->id, 'source_panel' => null]);
        $this->escalation->update(['submitter_id' => $this->colleague->id]);

        expect($this->service->workPageUrl($escalation, $this->owner))->toBe(url("/test/tickets/{$escalation->id}/view"))
            ->and($this->service->workPageUrl($this->escalation, $this->owner))->toBe(url("/test/tickets/{$this->openOriginal->id}/view"));
    });

    it('gives no link for an escalation no panel escalates to, rather than a requester\'s link', function () {
        $escalation = escalationFrom(attributes: ['submitter_id' => $this->owner->id, 'source_panel' => null, 'panel' => 'test3']);

        expect($this->service->getActionUrlFor($escalation, $this->owner))->toBeNull();
    });

    it('builds the links where the receiving panel has no tickets plugin', function () {
        // As on a queue worker that leaves the receiving panel's plugins out, under a host policy that lets its staff in.
        invade(Filament::getPanel('test2'))->plugins = [];
        Gate::policy(Ticket::class, TestTicketPolicy::class);

        expect($this->service->workPageUrl($this->escalation, $this->owner))
            ->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->openOriginal->id}"))
            ->and($this->service->workPageUrl($this->escalation, $this->padmission))
            ->toBe(url("/test2/tickets/{$this->escalation->id}/view"));
    });
});
