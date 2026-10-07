<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    foreach (['app', 'cm', 'pm', 'pt', 'admin', 'custom'] as $panelId) {
        app(PanelRegistry::class)->register(Panel::make()->id($panelId)->path($panelId)->plugin(clone TicketPlugin::get('test')));
    }

    $this->widgetUser = User::factory()->create();

    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);
});

// Panel names are host-defined; the same API serves every widget audience.
dataset('widget audiences and panels', function () {
    foreach (['app', 'cm', 'pm', 'pt', 'admin', 'custom'] as $panel) {
        foreach (['requester', 'supporter', 'staff'] as $audience) {
            yield "$audience in $panel" => [$panel, $audience];
        }
    }
});

function signInWidgetAudience(string $panelId, string $audience): User
{
    $user = test()->widgetUser;

    foreach (Filament::getPanels() as $panel) {
        TicketPlugin::get($panel->getId())->allSupportersQuery(
            fn () => User::query()->whereKey($audience === 'requester' ? [] : [$user->id])
        );
    }

    Filament::setCurrentPanel(null);
    test()->actingAs($user)->withHeader('X-Padmission-Tickets-Panel', $panelId);

    return $user;
}

it('defaults to open tickets and lets every audience include closed tickets without changing visibility', function (string $panelId, string $audience) {
    $user = signInWidgetAudience($panelId, $audience);

    $open = Ticket::factory()->open()->create(['panel' => $panelId, 'submitter_id' => $user->id]);
    $closed = Ticket::factory()->closed()->create(['panel' => $panelId, 'submitter_id' => $user->id]);
    // Widget tickets can be routed to another panel, including a linked original.
    $escalation = escalationFrom($panelId, ['submitter_id' => $user->id]);
    $linkedOpen = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => $panelId, 'submitter_id' => $user->id]);
    $linkedClosed = Ticket::factory()->closed()->create(['panel' => $panelId, 'submitter_id' => $user->id, 'linked_ticket_id' => $escalation->id]);
    Ticket::factory()->open()->create(['panel' => $panelId]);
    Ticket::factory()->closed()->create(['panel' => $panelId]);

    $ids = fn (array $parameters = []) => collect($this->getJson(route('padmission-tickets::api.index', $parameters))->assertOk()->json('tickets'))->pluck('id')->sort()->values()->all();
    $openIds = collect([$open->id, $linkedOpen->id])->sort()->values()->all();
    $allIds = collect([$open->id, $closed->id, $linkedOpen->id, $linkedClosed->id])->sort()->values()->all();

    expect($ids())->toBe($openIds)
        ->and($ids(['include_closed' => 1]))->toBe($allIds)
        ->and($ids(['include_closed' => 0]))->toBe($openIds)
        // The option belongs to each request, not the user's stored preferences.
        ->and($ids())->toBe($openIds);
})->with('widget audiences and panels');

it('keeps unread counts aligned with the selected list for every audience', function (string $panelId, string $audience) {
    $user = signInWidgetAudience($panelId, $audience);
    $open = Ticket::factory()->open()->create(['panel' => $panelId, 'submitter_id' => $user->id]);
    $closed = Ticket::factory()->closed()->create(['panel' => $panelId, 'submitter_id' => $user->id]);
    $escalation = escalationFrom($panelId, ['submitter_id' => $user->id]);
    $otherUserTicket = Ticket::factory()->open()->create(['panel' => $panelId]);

    foreach ([$open, $closed, $escalation, $otherUserTicket] as $ticket) {
        TicketActivity::factory()->create(['ticket_id' => $ticket->id, 'type' => ActivityType::Message, 'sender' => ActivitySender::Supporter]);
    }

    foreach ([0 => 1, 1 => 2] as $includeClosed => $expected) {
        $parameters = ['include_closed' => $includeClosed];
        $tickets = $this->getJson(route('padmission-tickets::api.index', $parameters))->assertOk()->json('tickets');
        $count = $this->getJson(route('padmission-tickets::api.unread-count', $parameters))->assertOk()->json('unread_count');

        expect($count)->toBe($expected)
            ->and(collect($tickets)->where('is_unread', true)->count())->toBe($count);
    }
})->with('widget audiences and panels');

it('opens a specific closed ticket omitted from the default list for every audience', function (string $panelId, string $audience) {
    $user = signInWidgetAudience($panelId, $audience);
    $closed = Ticket::factory()->closed()->create(['panel' => $panelId, 'submitter_id' => $user->id]);
    TicketActivity::factory()->create([
        'ticket_id' => $closed->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::Supporter,
        'content' => 'The old answer',
    ]);

    $this->getJson(route('padmission-tickets::api.index'))->assertOk()->assertJsonCount(0, 'tickets');
    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $closed]))
        ->assertOk()
        ->assertJsonPath('ticket.is_closed', true)
        ->assertJsonFragment(['content' => 'The old answer']);
})->with('widget audiences and panels');

it('opens closed escalations and their linked originals for both support teams while the list stays open-only', function (string $panelId) {
    $user = signInWidgetAudience($panelId, 'supporter');
    TicketPlugin::get('app')->allowLinkedTicketsTo(['admin']);
    Ticket::factory()->closed()->create(['panel' => $panelId, 'submitter_id' => $user->id]);
    $escalation = Ticket::factory()->closed()->create(['panel' => 'admin', 'source_panel' => 'app', 'submitter_id' => $panelId === 'app' ? $user->id : User::factory()]);
    $escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System);
    $original = Ticket::factory()->closed()->create(['panel' => 'app', 'linked_ticket_id' => $escalation->id]);

    $this->getJson(route('padmission-tickets::api.index'))->assertOk()->assertJsonCount(0, 'tickets');

    foreach ([$escalation, $original] as $ticket) {
        $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
            ->assertOk()->assertJsonPath('ticket.is_closed', true);
    }
})->with(['app', 'admin']);

it('reports closed tickets only from the viewers widget list', function (bool $hasClosedTickets, int $includeClosed) {
    $user = signInWidgetAudience('app', 'requester');
    Ticket::factory()->closed()->create(['panel' => 'app']);
    escalationFrom('app', ['submitter_id' => $user->id], 'closed');

    if ($hasClosedTickets) {
        Ticket::factory()->closed()->create(['panel' => 'admin', 'source_panel' => 'app', 'submitter_id' => $user->id]);
    }

    $this->getJson(route('padmission-tickets::api.index', ['include_closed' => $includeClosed]))
        ->assertOk()
        ->assertJsonPath('has_closed_tickets', $hasClosedTickets)
        ->assertJsonCount($hasClosedTickets && $includeClosed ? 1 : 0, 'tickets');
})->with([false, true])->with([0, 1]);
