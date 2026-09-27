<?php

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\RemoveFromEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

use function Pest\Laravel\partialMock;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->login();
});

it('is visible when linked tickets enabled and ticket has no parent', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(escalateAction());
});

it('is hidden when linked tickets disabled', function () {
    TicketPlugin::get()->allowLinkedTicketsTo([]);

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee(__('padmission-tickets::tickets.actions.create_linked_ticket.label'));
});

it('is hidden when ticket already has parent', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $parentTicket = Ticket::factory()->open()->create();
    $childTicket = Ticket::factory()->open()->create(['linked_ticket_id' => $parentTicket->id]);

    expect(CreateLinkedTicketAction::isAvailableFor($childTicket))->toBeFalse();

    Livewire::test(ViewTicket::class, ['record' => $childTicket->id])
        ->assertDontSee(__('padmission-tickets::tickets.actions.create_linked_ticket.label'));
});

it('sets default subject', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $originalTicket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->mountAction(escalateAction())
        ->assertSchemaComponentStateSet('subject', $originalTicket->subject);
});

it('creates linked ticket successfully', function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $originalTicket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);
    $currentPanel = Filament::getCurrentOrDefaultPanel()->getId();

    $messageContent = 'This is the initial message for the linked ticket';

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(escalateAction(), [
            'subject' => 'Linked Test Ticket',
            'message' => $messageContent,
        ])
        ->assertHasNoFormErrors();

    expect(Ticket::count())->toBe(2);

    $newTicket = Ticket::where('subject', 'Linked Test Ticket')->first();

    expect($newTicket)
        ->panel->toBe($currentPanel)
        ->source_panel->toBe($currentPanel)
        ->subject->toBe('Linked Test Ticket')
        ->submitter_id->toBe(auth()->id())
        ->turn->toBe(Turn::Supporter)
        ->status_id->toBe(TicketStatus::getOpenStatuses()->first()->id)
        ->priority_id->toBe(1);

    expect($originalTicket->refresh())
        ->linked_ticket_id->toBe($newTicket->id);

    // Verify the message was persisted as a ticket activity
    $messageActivity = $newTicket->ticketActivities()
        ->where('type', ActivityType::Message)
        ->first();

    expect($messageActivity)
        ->not->toBeNull()
        ->content->toContain($messageContent)
        ->user_id->toBe(auth()->id());
});

it('creates linked ticket for different panel', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $originalTicket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    $messageContent = 'Cross-panel message content';

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(escalateAction(), [
            'subject' => 'Cross-Panel Ticket',
            'message' => $messageContent,
        ])
        ->assertHasNoFormErrors();

    $newTicket = Ticket::where('subject', 'Cross-Panel Ticket')->first();

    expect($newTicket)
        ->not->toBeNull()
        ->source_panel->toBe(Filament::getCurrentOrDefaultPanel()->getId());

    // Verify message persistence for cross-panel ticket
    expect($newTicket->ticketActivities()->where('type', ActivityType::Message)->first())
        ->not->toBeNull()
        ->content->toContain($messageContent);
});

it('links the ticket to the escalation it opens', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $originalTicket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    $component = Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(escalateAction(), [
            'subject' => 'Data Update Test',
            'message' => tiptapDocument('Test message for data update'),
        ])
        ->assertHasNoFormErrors();

    $newTicket = Ticket::where('subject', 'Data Update Test')->first();
    expect($originalTicket->refresh()->linked_ticket_id)->toBe($newTicket->id);
});

it('sends success notification with action link', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $originalTicket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(escalateAction(), [
            'subject' => 'Notification Test',
            'message' => tiptapDocument('Notification test message'),
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();
});

it('shows a link to the ticket in the users existing panel', function () {
    // The partial-mock user cannot survive queue serialization when the
    // TicketCreatedEvent notification job runs inline on the sync driver.
    Queue::fake();

    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $mockedUser = partialMock(User::class)
        ->shouldReceive('canAccessPanel')
        ->andReturn(false)
        ->getMock();

    $this->actingAs($mockedUser);

    $originalTicket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(escalateAction(), [
            'subject' => 'Notification Test',
            'message' => tiptapDocument('Notification test message'),
        ])
        ->assertHasNoFormErrors();

    $newTicket = Ticket::where('subject', 'Notification Test')->sole();

    // Assert through Filament's own testing API rather than reading
    // `filament.notifications` off the session: since Filament v4.10.2 a
    // dehydrating Livewire request moves pending notifications to
    // `filament.claimed_notifications`, so the original key is already empty
    // by the time the test reads it.
    Notification::assertNotified(
        Notification::make()
            ->success()
            ->title(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.success.title'))
            ->body(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.success.body'))
            ->actions([
                Action::make('link')
                    ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.success.action_label'))
                    // The user cannot access the panel the linked ticket was created in,
                    // so the link has to point at the ticket in their existing panel.
                    ->url(TicketResource::getUrl('view', ['record' => $newTicket], panel: 'test')),
            ])
    );
});

it('requires subject field', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(escalateAction(), [
            'subject' => '',
            'message' => tiptapDocument('Message without subject'),
        ])
        ->assertHasFormErrors(['subject']);
});

it('requires message field', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(escalateAction(), [
            'subject' => 'Subject without message',
            'message' => tiptapDocument('<p></p>'),
        ])
        ->assertHasFormErrors(['message']);
});

it('explains instead of failing when the target panel has no statuses', function () {
    Exceptions::fake();
    TicketStatus::withoutGlobalScopes()->where('panel', 'test2')->forceDelete();

    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test2']);

    $originalTicket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(escalateAction(), [
            'subject' => 'Escalated',
            'message' => tiptapDocument('Please help'),
        ])
        ->assertNotified(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.not_configured.title'));

    expect(Ticket::withoutGlobalScopes()->where('panel', 'test2')->exists())->toBeFalse()
        ->and($originalTicket->refresh()->linked_ticket_id)->toBeNull();

    Exceptions::assertReported(RuntimeException::class);
});

it('names the support team it escalates to', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHasLabel(escalateAction(), 'Escalate to Platform Support')
        ->mountAction(escalateAction())
        ->assertMountedActionModalSee('Opens a separate ticket for Platform Support');
});

it('keeps the generic label when the target panel has no support team name', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test2']);

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHasLabel(escalateAction(), __('padmission-tickets::tickets.actions.create_linked_ticket.label'));
});

it('keeps the generic label when it can escalate to more than one team', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test2', 'test3']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');
    TicketPlugin::get('test3')->supportTeamName('Billing');

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHasLabel(escalateAction(), __('padmission-tickets::tickets.actions.create_linked_ticket.label'))
        ->mountAction(escalateAction())
        ->assertMountedActionModalSee(['Platform Support', 'Billing']);
});

it('submits with an escalate button', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test2']);

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(escalateAction())
        ->assertMountedActionModalSee(__('padmission-tickets::tickets.actions.create_linked_ticket.submit'))
        ->assertMountedActionModalDontSee('Submit');
});

function escalateAction(): TestAction
{
    return TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form');
}

describe('Escalating again', function () {
    beforeEach(function () {
        (new TicketPrioritySeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('opens a new escalation after the old one closed and notes the move on all three tickets', function () {
        $oldEscalation = Ticket::factory()->closed()->create(['panel' => 'test2']);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $oldEscalation->id]);
        $updatedAt = $original->refresh()->updated_at->toDateTimeString();

        $this->travel(1)->hour();

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertActionHasLabel(escalateAction(), 'Escalate to Platform Support again')
            ->assertDontSee('Remove from escalation')
            ->mountAction(escalateAction())
            ->assertMountedActionModalSee([
                'Escalate to Platform Support again',
                'Opens a new escalation to Platform Support. The closed escalation stays in this ticket\'s history.',
            ]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->callAction(escalateAction(), [
                'subject' => 'Still broken',
                'message' => tiptapDocument('It is back'),
            ])
            ->assertHasNoFormErrors();

        $newEscalation = Ticket::query()->where('subject', 'Still broken')->sole();
        $types = fn (Ticket $ticket): array => $ticket->ticketActivities()->orderBy('id')->get()
            ->map(fn (TicketActivity $activity): array => [$activity->type, $activity->data])
            ->filter(fn (array $row): bool => $row[0] !== ActivityType::Message)
            ->values()
            ->all();

        expect($original->refresh())
            ->linked_ticket_id->toBe($newEscalation->id)
            ->updated_at->toDateTimeString()->toBe($updatedAt)
            ->and($types($original))->toBe([
                [ActivityType::RemovedFromEscalation, ['escalation' => $oldEscalation->id]],
                [ActivityType::AddedToEscalation, ['escalation' => $newEscalation->id]],
            ])
            ->and($types($oldEscalation))->toBe([[ActivityType::OriginalRemoved, ['original' => $original->id]]])
            ->and($types($newEscalation))->toBe([[ActivityType::OriginalAdded, ['original' => $original->id]]]);
    });

    it('escalates a ticket whose escalation was deleted or is missing as if for the first time', function (bool $missing) {
        $deleted = Ticket::factory()->open()->create(['panel' => 'test2']);
        $deleted->delete();
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $missing ? 999999 : $deleted->id]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertActionHasLabel(escalateAction(), 'Escalate to Platform Support')
            ->mountAction(escalateAction())
            ->assertMountedActionModalSee('Opens a separate ticket for Platform Support. This ticket stays with your team.')
            ->assertMountedActionModalDontSee(['again', 'The closed escalation stays in this ticket']);
    })->with(['deleted' => false, 'missing' => true]);

    it('uses the generic wording to escalate again when the team has no name', function () {
        TicketPlugin::get('test2')->supportTeamName(null);

        $oldEscalation = Ticket::factory()->closed()->create(['panel' => 'test2']);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $oldEscalation->id]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertActionHasLabel(escalateAction(), 'Escalate again')
            ->mountAction(escalateAction())
            ->assertMountedActionModalSee([
                'Escalate again',
                'Opens a new escalation to the team you escalated to. The closed escalation stays in this ticket\'s history.',
            ]);
    });

    it('adds a ticket whose escalation was deleted to another escalation', function () {
        $deleted = Ticket::factory()->open()->create(['panel' => 'test2']);
        $deleted->delete();
        $target = Ticket::factory()->open()->create(['panel' => 'test2']);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $deleted->id]);

        expect(CreateLinkedTicketAction::isAvailableFor($original))->toBeTrue()
            ->and(resolve(TicketEscalationLinks::class)->addToEscalation($original, $target->id))->toBeNull()
            ->and($original->refresh()->linked_ticket_id)->toBe($target->id)
            ->and($original->ticketActivities()->where('type', ActivityType::RemovedFromEscalation)->value('data'))->toBe(['escalation' => $deleted->id]);
    });

    it('replaces a link to an escalation that no longer exists', function () {
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => 999999]);

        expect(CreateLinkedTicketAction::isAvailableFor($original))->toBeTrue()
            ->and(DB::transaction(fn (): bool => resolve(TicketEscalationLinks::class)->canOpenEscalation($original)))->toBeTrue()
            ->and($original->refresh()->linked_ticket_id)->toBeNull()
            ->and($original->ticketActivities()->where('type', ActivityType::RemovedFromEscalation)->exists())->toBeTrue();
    });

    it('keeps the old link when the chosen escalation cannot be joined', function () {
        $oldEscalation = Ticket::factory()->closed()->create(['panel' => 'test2']);
        $closedTarget = Ticket::factory()->closed()->create(['panel' => 'test2']);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $oldEscalation->id]);

        expect(resolve(TicketEscalationLinks::class)->addToEscalation($original, $closedTarget->id))->toBe(TicketEscalationLinks::NOT_LINKABLE)
            ->and($original->refresh()->linked_ticket_id)->toBe($oldEscalation->id)
            ->and($original->ticketActivities()->where('type', ActivityType::RemovedFromEscalation)->exists())->toBeFalse();
    });

    it('still refuses a ticket whose escalation is open', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        $other = Ticket::factory()->open()->create(['panel' => 'test2']);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);
        $links = resolve(TicketEscalationLinks::class);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertActionVisible(TestAction::make(RemoveFromEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'))
            ->assertDontSee(['Escalate to Platform Support', 'Add to an existing escalation']);

        expect(DB::transaction(fn (): bool => $links->canOpenEscalation($original)))->toBeFalse()
            ->and($links->addToEscalation($original, $other->id))->toBe(TicketEscalationLinks::ALREADY_ESCALATED)
            ->and($original->refresh()->linked_ticket_id)->toBe($escalation->id)
            ->and($original->ticketActivities()->where('type', ActivityType::RemovedFromEscalation)->exists())->toBeFalse();
    });

    it('is hidden on a closed ticket', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        $closed = Ticket::factory()->closed()->create(['linked_ticket_id' => null]);
        $closedAndEscalated = Ticket::factory()->closed()->create(['linked_ticket_id' => $escalation->id]);

        expect(CreateLinkedTicketAction::isAvailableFor($closed))->toBeFalse();

        Livewire::test(ViewTicket::class, ['record' => $closed->id])
            ->assertDontSee(['Escalate to Platform Support', 'Add to an existing escalation']);

        Livewire::test(ViewTicket::class, ['record' => $closedAndEscalated->id])
            ->assertDontSee('Remove from escalation');
    });
});
