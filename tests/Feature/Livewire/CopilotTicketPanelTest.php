<?php

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Livewire\CopilotTicketPanel;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\TicketActivityService;

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
