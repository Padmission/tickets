<?php

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Livewire\CopilotTicketPanel;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

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

it('tells its chat the ticket closed when it is resolved, so the reply box goes at once', function () {
    (new TicketStatusSeeder)->run();

    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSeeHtml("window.addEventListener('ticket-chat-changed'")
        ->call('resolveTicket')
        ->assertDispatched('ticket-chat-changed', ticketId: $this->ticket->id, canReply: false);
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

it('redraws the header as closed, without Resolve, when the chat sees the ticket close', function () {
    $open = TicketStatus::factory()->create(['panel' => $this->ticket->panel, 'display_name' => 'Waiting on support', 'order' => 1]);
    TicketStatus::factory()->create([
        'panel' => $this->ticket->panel,
        'display_name' => 'All done',
        'order' => TicketStatus::query()->withoutGlobalScopes()->where('panel', $this->ticket->panel)->max('order') + 1,
    ]);
    $this->ticket->update(['status_id' => $open->id]);

    $panel = Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSeeHtml('this.handleTicketClosed = () => this.$wire.$refresh()')
        ->assertSeeHtml("addEventListener('ticket-closed', this.handleTicketClosed)")
        ->assertSeeHtml('x-on:click="$refs.resolveDialog.showModal()"')
        ->assertSeeInOrder([$this->ticket->subject, 'Waiting on support']);

    $this->ticket->close(closedById: User::factory()->create()->id);

    $panel->call('$refresh')
        ->assertDontSeeHtml('x-on:click="$refs.resolveDialog.showModal()"')
        ->assertDontSee('Resolve this ticket?')
        ->assertDontSee('Waiting on support')
        ->assertSeeInOrder([$this->ticket->subject, 'All done']);
});

it('gives its chat the display timezone, so its times match the ticket page', function () {
    TicketPlugin::get()->displayTimezone(fn (): string => 'America/Phoenix');

    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSeeHtml('timezone="America/Phoenix"')
        ->call('showCreateForm')
        ->assertSeeHtml('timezone="America/Phoenix"');
});

it('tells the assistant which ticket it shows, and when it went back to the list', function () {
    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertDispatched('padmission-copilot-ticket-shown', ticketId: $this->ticket->id)
        ->call('showList')
        ->assertDispatched('padmission-copilot-ticket-shown', ticketId: null)
        ->call('showCreateForm')
        ->assertDispatched('padmission-copilot-ticket-shown', ticketId: null);

    Livewire::test(CopilotTicketPanel::class)->assertDispatched('padmission-copilot-ticket-shown', ticketId: null);
});

it('tears its chat listeners down with Alpine\'s own destroy, not a $cleanup the hosts\' Livewire does not have', function () {
    Livewire::test(CopilotTicketPanel::class)->assertDontSeeHtml('$cleanup');
    Livewire::test(CopilotTicketPanel::class)->call('showCreateForm')->assertDontSeeHtml('$cleanup')->assertSeeHtml('destroy()');
    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])->assertDontSeeHtml('$cleanup')->assertSeeHtml('destroy()');
});
