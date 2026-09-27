<?php

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\HandOverEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->owner = User::factory()->create(['name' => 'Test Admin']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->outsider = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->padmission = User::factory()->create(['name' => 'Kevin McKee']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->owner->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Padmission');
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->padmission->id));

    $this->escalation = Ticket::factory()->open()->create([
        'panel' => 'test2',
        'source_panel' => 'test',
        'submitter_id' => $this->owner->id,
        'assignee_id' => $this->padmission->id,
        'turn' => Turn::Supporter,
    ]);
    $this->original = Ticket::factory()->open()->create([
        'linked_ticket_id' => $this->escalation->id,
        'submitter_id' => $this->outsider->id,
        'assignee_id' => $this->owner->id,
        'turn' => Turn::Supporter,
    ]);
});

function handOverHint(): TestAction
{
    return TestAction::make(HandOverEscalationAction::class)->schemaComponent('submitter', schema: 'form');
}

function takeOverInBox(): TestAction
{
    return TestAction::make('take-over-escalation')->schemaComponent('escalationActions', schema: 'form');
}

it('lets the owner hand the escalation to a colleague from Handled by', function () {
    Event::fake([TicketHandedOverEvent::class]);
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertActionHasLabel(handOverHint(), 'Hand over')
        ->mountAction(handOverHint())
        ->assertMountedActionModalSee([
            'Hand over this escalation',
            'Choose who on your team talks with Padmission here. Padmission\'s replies will go to them, and they answer the requesters on their tickets. Padmission\'s assignee doesn\'t change.',
            'Hand over to',
        ])
        ->fillForm(['new_owner' => $this->colleague->id])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Escalation handed to Maria Lopez')
        ->assertRedirect(TicketResource::getUrl('index', ['tab' => 'linked']));

    $activity = $this->escalation->ticketActivities()->where('type', ActivityType::HandedOver)->sole();

    expect($this->escalation->refresh()->submitter_id)->toBe($this->colleague->id)
        ->and($activity->sender)->toBe(ActivitySender::System)
        ->and($activity->user_id)->toBe($this->owner->id)
        ->and($activity->data)->toBe(['from' => $this->owner->id, 'to' => $this->colleague->id])
        ->and($activity->content)->toBe('Test Admin handed this escalation to Maria Lopez')
        ->and($this->original->refresh()->assignee_id)->toBe($this->owner->id);

    Event::assertDispatched(TicketHandedOverEvent::class, fn (TicketHandedOverEvent $event): bool => $event->ticket->is($this->escalation)
        && $event->actor->is($this->owner)
        && $event->fromId === $this->owner->id
        && $event->toId === $this->colleague->id);
});

it('offers only the escalating team, without the owner, and refuses anyone else', function () {
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->mountAction(handOverHint())
        ->assertMountedActionModalSee('Maria Lopez')
        ->assertMountedActionModalDontSee(['Aisha Brooks', 'Kevin McKee']);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->callAction(handOverHint(), ['new_owner' => $this->outsider->id]);

    expect($this->escalation->refresh()->submitter_id)->toBe($this->owner->id)
        ->and($this->escalation->ticketActivities()->where('type', ActivityType::HandedOver)->exists())->toBeFalse();
});

it('explains when nobody else can take the escalation', function () {
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($this->owner->id))
        ->assignableUsersDescription('Give a teammate the Ticket Support role.');
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->mountAction(handOverHint())
        ->assertMountedActionModalSee(['Nobody else can take this escalation.', 'Give a teammate the Ticket Support role.']);
});

it('lets a colleague take over from the original\'s Escalation box', function () {
    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertSee('Test Admin handles the conversation with Padmission.')
        ->assertDontSee('Open the escalation')
        ->assertActionHasLabel(takeOverInBox(), 'Take over')
        ->mountAction(takeOverInBox())
        ->assertMountedActionModalSee([
            'Take over this escalation?',
            'Padmission\'s replies will come to you. Test Admin is told.',
        ])
        ->callMountedAction()
        ->assertNotified('You now handle this escalation')
        ->assertSee('You handle the conversation with Padmission.');

    $activity = $this->escalation->ticketActivities()->where('type', ActivityType::HandedOver)->sole();

    expect($this->escalation->refresh()->submitter_id)->toBe($this->colleague->id)
        ->and($activity->user_id)->toBe($this->colleague->id)
        ->and($activity->content)->toBe('Maria Lopez took over this escalation from Test Admin');
});

it('offers Take over in the status line when the other team replied to a colleague', function () {
    $this->escalation->ticketActivities()->create(['type' => ActivityType::Message, 'sender' => ActivitySender::Supporter, 'user_id' => $this->padmission->id, 'content' => 'Which household?']);
    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertSee('Padmission replied to Test Admin on the escalation.')
        ->assertActionVisible('takeOver')
        ->callAction('takeOver')
        ->assertNotified('You now handle this escalation');

    expect($this->escalation->refresh()->submitter_id)->toBe($this->colleague->id);
});

it('shows Hand over or Take over on the Escalations tab rows', function () {
    $this->login($this->colleague);

    Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
        ->assertCanSeeTableRecords([$this->escalation])
        ->assertSee('Take over')
        ->assertActionHasLabel(TestAction::make(HandOverEscalationAction::class)->table($this->escalation), 'Take over')
        ->assertActionHidden(TestAction::make('view')->table($this->escalation))
        ->callAction(TestAction::make(HandOverEscalationAction::class)->table($this->escalation))
        ->assertActionHasLabel(TestAction::make(HandOverEscalationAction::class)->table($this->escalation), 'Hand over')
        ->assertActionVisible(TestAction::make('view')->table($this->escalation));

    expect($this->escalation->refresh()->submitter_id)->toBe($this->colleague->id);
});

it('is not offered on originals, closed escalations or where the escalation was sent', function () {
    $this->login($this->owner);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->assertActionHidden(TestAction::make(HandOverEscalationAction::class)->table($this->original));

    expect(HandOverEscalationAction::isAvailableFor($this->original))->toBeFalse();

    Filament::setCurrentPanel('test2');
    $this->login($this->padmission);

    expect(HandOverEscalationAction::isAvailableFor($this->escalation->refresh()))->toBeFalse();

    Filament::setCurrentPanel('test');
    $this->login($this->owner);
    $this->escalation->close(closedById: $this->padmission->id);

    expect(HandOverEscalationAction::isAvailableFor($this->escalation->refresh()))->toBeFalse();

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertDontSee(['Hand over', 'Take over']);
});

it('refuses a hand over once the escalation closed or changed hands', function () {
    $this->login($this->owner);
    $links = resolve(TicketEscalationLinks::class);

    expect($links->handOver($this->escalation, $this->colleague->id, $this->padmission->id))->toBeFalse()
        ->and($this->escalation->refresh()->submitter_id)->toBe($this->owner->id);

    $this->escalation->close(closedById: $this->padmission->id);

    expect($links->handOver($this->escalation, $this->colleague->id, $this->owner->id))->toBeFalse()
        ->and($this->escalation->refresh()->submitter_id)->toBe($this->owner->id)
        ->and($this->escalation->ticketActivities()->where('type', ActivityType::HandedOver)->exists())->toBeFalse();
});

it('moves access, replies, My Escalations and notifications to the new owner', function () {
    $this->login($this->owner);
    resolve(TicketEscalationLinks::class)->handOver($this->escalation, $this->colleague->id, $this->owner->id);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])->assertForbidden();

    Livewire::test(ListTickets::class, ['activeTab' => 'my_linked'])
        ->assertCanNotSeeTableRecords([$this->escalation]);

    expect(resolve(TicketAuth::class)->canReply($this->escalation->refresh(), $this->owner))->toBeFalse()
        ->and(resolve(TicketAuth::class)->canReply($this->escalation, $this->colleague))->toBeTrue();

    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertSuccessful();

    Livewire::test(ListTickets::class, ['activeTab' => 'my_linked'])
        ->assertCanSeeTableRecords([$this->escalation]);

    $this->login($this->padmission);
    $recipients = resolve(NotificationRecipientService::class)
        ->getNotificationRecipients(new TicketActivityEvent($this->escalation, ActivityType::Message, null, $this->padmission));

    expect($recipients->map->getKey()->all())->toBe([$this->colleague->id])
        ->and(resolve(TicketActivityService::class)->getActivityTypesForSender($this->escalation, ActivitySender::User, $this->colleague))
        ->toContain(ActivityType::HandedOver)
        ->and($this->escalation->ticketActivities()->where('type', ActivityType::HandedOver)->sole()->content)
        ->toBe('Test Admin handed this escalation to Maria Lopez');
});

it('asks Take over as a centred confirm, whatever the host\'s dialog style', function () {
    Action::configureUsing(fn (Action $action) => $action->slideOver());

    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertActionExists(takeOverInBox(), fn (HandOverEscalationAction $action): bool => ! $action->isModalSlideOver() && $action->isConfirmationRequired());

    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertActionExists(handOverHint(), fn (HandOverEscalationAction $action): bool => $action->isModalSlideOver() && ! $action->isConfirmationRequired());
});

it('asks the escalating team\'s pool once per request, however many rows it offers Take over on', function () {
    $calls = 0;
    TicketPlugin::get()->allSupportersQuery(function () use (&$calls) {
        $calls++;

        return User::query()->whereKey([$this->owner->id, $this->colleague->id]);
    });
    $this->login($this->colleague);

    $count = function () use (&$calls): int {
        $calls = 0;
        Once::flush();
        Livewire::test(ListTickets::class, ['activeTab' => 'linked'])->assertSee('Take over');

        return $calls;
    };

    $one = $count();

    foreach (range(1, 3) as $ignored) {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->owner->id]);
        Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);
    }

    expect($count())->toBe($one);
});

it('closes a stale Take over dialog when the escalation changed hands meanwhile', function () {
    $this->login($this->colleague);

    $component = Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->mountAction(takeOverInBox());

    $this->escalation->forceFill(['submitter_id' => $this->padmission->id])->save();

    $component->callMountedAction()
        ->assertNotified(__('padmission-tickets::tickets.actions.hand_over.refused'))
        ->assertHasNoActionErrors();

    expect($component->instance()->mountedActions)->toBe([])
        ->and($this->escalation->refresh()->submitter_id)->toBe($this->padmission->id);
});

it('stores the new owner with the key\'s own type', function () {
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->callAction(handOverHint(), ['new_owner' => (string) $this->colleague->id]);

    $activity = $this->escalation->ticketActivities()->where('type', ActivityType::HandedOver)->sole();

    expect($activity->data['to'])->toBe($this->colleague->id)
        ->and($this->escalation->refresh()->getRawOriginal('submitter_id'))->toBe($this->colleague->id);
});

it('does not offer Take over on a closed original', function () {
    $this->login($this->colleague);
    $this->original->close(closedById: $this->owner->id);

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertDontSee('Take over');
});
