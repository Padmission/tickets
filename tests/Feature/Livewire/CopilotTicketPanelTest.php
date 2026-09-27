<?php

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Livewire\CopilotTicketPanel;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    Event::fake();

    $this->user = $this->login();
    $this->ticket = Ticket::factory()->create(['submitter_id' => $this->user->id]);
    $this->reply = TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
    ]);
});

it('marks the ticket it opens on mount as seen', function () {
    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSet('activeTicketId', $this->ticket->id);

    expect($this->ticket->ticketUserStates()->where('user_id', $this->user->id)->value('last_seen_activity_id'))
        ->toBe($this->reply->id);
});

it('opens the ticket without marking it seen while seen tracking is skipped', function () {
    app(TicketActivityService::class)->skipSeenTrackingWhen(fn () => true);

    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSet('activeTicketId', $this->ticket->id)
        ->call('selectTicket', $this->ticket->id)
        ->assertSet('view', 'detail');

    expect($this->ticket->ticketUserStates()->where('user_id', $this->user->id)->exists())->toBeFalse();
});

it('asks before resolving, and only the dialog\'s button resolves', function () {
    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSeeHtml('x-on:click="$refs.resolveDialog.showModal()"')
        ->assertDontSeeHtml('<button
                        type="button"
                        wire:click="resolveTicket"')
        ->assertSee('Resolve this ticket?')
        ->assertSee('This closes the ticket and lets support know. Nobody can reply to it after that, and it can\'t be reopened.')
        ->assertSee('Resolve ticket');
});

it('resolves the ticket once confirmed', function () {
    (new TicketStatusSeeder)->run();

    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->call('resolveTicket')
        ->assertDontSee('Resolve this ticket?');

    expect($this->ticket->refresh()->isClosed)->toBeTrue();
});

it('says a ticket support already closed has nothing to resolve, and leaves its close alone', function () {
    (new TicketStatusSeeder)->run();

    $panel = Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id]);

    $supporter = User::factory()->create();
    $this->ticket->close(closedById: $supporter->id);

    $panel->call('resolveTicket')
        ->assertNotified(__('padmission-tickets::tickets.copilot.already_closed'))
        ->assertDontSee('Resolve this ticket?');

    expect($this->ticket->refresh()->closed_by)->toBe($supporter->id);
});

it('redraws the header when the chat sees the ticket close', function () {
    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSeeHtml("addEventListener('ticket-closed', handleTicketClosed)");
});
