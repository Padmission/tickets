<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    Queue::fake();
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->owner = User::factory()->create(['name' => 'Test Admin']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->third = User::factory()->create(['name' => 'Dana Whitaker']);
    $this->aisha = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->padmission = User::factory()->create(['name' => 'Kevin McKee']);
    $this->alessa = User::factory()->create(['name' => 'Alessa Support']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->owner->id, $this->colleague->id, $this->third->id]));
    TicketPlugin::get('test2')->supportTeamName('Padmission');
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey([$this->padmission->id, $this->alessa->id]));

    $this->escalation = escalationFrom(attributes: [
        'subject' => 'Recert rent is wrong',
        'submitter_id' => $this->owner->id,
        'assignee_id' => $this->padmission->id,
    ]);
    $this->original = Ticket::factory()->open()->create([
        'linked_ticket_id' => $this->escalation->id,
        'submitter_id' => $this->aisha->id,
        'assignee_id' => $this->colleague->id,
    ]);
});

/*
 * What happened while the ticket was set up has already been told.
 */
function relevanceToldSoFar(Ticket $ticket, User ...$users): void
{
    foreach ($users as $user) {
        resolve(TicketActivityService::class)->markAsSent($ticket, $user, (int) $ticket->ticketActivities()->max('id'));
    }
}

function relevanceMessage(Ticket $ticket, User $author, ActivitySender $sender, string $content): TicketActivity
{
    return TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $author->id,
        'sender' => $sender,
        'type' => ActivityType::Message,
        'content' => $content,
    ]);
}

it('drops a debounced email to someone the ticket was taken from before it was sent', function () {
    Notification::fake();
    relevanceMessage($this->original, $this->aisha, ActivitySender::User, 'Any news?');
    $event = new TicketActivityEvent($this->original, ActivityType::Message, actor: $this->aisha);

    $job = new NotificationJob($this->colleague, $this->original, $event);
    $this->original->update(['assignee_id' => $this->owner->id]);
    $job->handle();

    Notification::assertNothingSentTo($this->colleague);

    (new NotificationJob($this->owner, $this->original, $event))->handle();

    Notification::assertSentTo($this->owner, TicketNotification::class);
});

it('drops a debounced email to a supporter who can no longer see an unassigned ticket', function () {
    Notification::fake();
    $this->original->update(['assignee_id' => null]);
    relevanceMessage($this->original, $this->aisha, ActivitySender::User, 'Any news?');
    $event = new TicketActivityEvent($this->original, ActivityType::Message, actor: $this->aisha);

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($this->owner->id));
    (new NotificationJob($this->colleague, $this->original, $event))->handle();
    (new NotificationJob($this->owner, $this->original, $event))->handle();

    Notification::assertNothingSentTo($this->colleague);
    Notification::assertSentTo($this->owner, TicketNotification::class);
});

it('leaves the close out of Ticket updated, since Ticket closed tells it', function () {
    relevanceToldSoFar($this->escalation, $this->padmission);
    relevanceMessage($this->escalation, $this->owner, ActivitySender::User, 'Confirmed, thanks.');
    $this->actingAs($this->owner);
    $this->escalation->close(closedById: $this->owner->id);
    auth()->logout();

    $update = new TicketNotification($this->escalation->refresh(), new TicketActivityEvent($this->escalation, ActivityType::Message, actor: $this->owner));
    $mail = $update->toMail($this->padmission);

    expect($update->shouldSend($this->padmission))->toBeTrue()
        ->and($mail->viewData['activities']->pluck('type')->all())->toBe([ActivityType::Message]);

    $onlyTheClose = new TicketNotification($this->escalation, new TicketClosedEvent($this->escalation, $this->owner));
    $onlyTheUpdate = new TicketNotification($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Closed, actor: $this->owner));

    expect($onlyTheClose->shouldSend($this->padmission))->toBeTrue()
        ->and($onlyTheUpdate->shouldSend($this->padmission))->toBeFalse();
});

it('tells nobody about what they did themselves', function () {
    relevanceToldSoFar($this->escalation, $this->padmission);
    relevanceToldSoFar($this->original, $this->colleague);
    relevanceMessage($this->escalation, $this->padmission, ActivitySender::Supporter, 'Which household?');
    $this->escalation->close(closedById: $this->padmission->id);
    $this->original->addTicketActivity(ActivityType::AssigneeChanged, ActivitySender::System, $this->owner->id, ['from' => null, 'to' => $this->colleague->id]);

    expect((new TicketNotification($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Closed, actor: $this->padmission)))->shouldSend($this->padmission))->toBeFalse()
        ->and((new TicketNotification($this->escalation, new TicketClosedEvent($this->escalation, $this->padmission)))->shouldSend($this->padmission))->toBeFalse()
        ->and((new TicketNotification($this->original, new TicketAssignedEvent($this->original, $this->colleague)))->shouldSend($this->colleague))->toBeFalse()
        ->and((new TicketNotification($this->original, new TicketAssignedEvent($this->original, $this->owner)))->shouldSend($this->colleague))->toBeTrue();
});

it('sends no Ticket updated whose only news is the recipient\'s own hand over', function () {
    relevanceToldSoFar($this->escalation, $this->owner, $this->padmission);
    $this->escalation->addTicketActivity(ActivityType::HandedOver, ActivitySender::System, $this->owner->id, ['from' => $this->owner->id, 'to' => $this->colleague->id]);
    $this->escalation->update(['submitter_id' => $this->colleague->id]);
    $event = new TicketActivityEvent($this->escalation, ActivityType::HandedOver, actor: $this->owner);

    expect((new TicketNotification($this->escalation, $event))->shouldSend($this->owner))->toBeFalse()
        ->and((new TicketNotification($this->escalation, $event))->shouldSend($this->padmission))->toBeTrue();
});

it('names the other team\'s people and the person assigned, found through the escalation\'s panel', function () {
    TicketPlugin::get('test2')->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('acting-tenant'));
    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($this->padmission->id));

    $reply = relevanceMessage($this->escalation, $this->padmission, ActivitySender::Supporter, 'Which household?');
    $assigned = $this->escalation->addTicketActivity(ActivityType::AssigneeChanged, ActivitySender::System, $this->alessa->id, ['from' => null, 'to' => $this->padmission->id]);

    $notification = new TicketNotification($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message, actor: $this->padmission));

    expect($notification->senderName($reply->fresh()))->toBe('Kevin McKee')
        ->and($notification->activityContent($assigned))->toBe('Assigned to Kevin McKee');
})->after(fn () => User::clearBootedModels());

it('names the team or the organization, never Support, for a writer it cannot find', function () {
    TicketPlugin::get('test2')->describeTicketOriginUsing(fn () => 'Test Organization');
    $reply = relevanceMessage($this->escalation, $this->padmission, ActivitySender::Supporter, 'Which household?');
    $question = relevanceMessage($this->escalation, $this->owner, ActivitySender::User, 'Household 14.');
    $reply->forceFill(['user_id' => 99998])->saveQuietly();
    $question->forceFill(['user_id' => 99999])->saveQuietly();

    $notification = new TicketNotification($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message, actor: $this->owner));
    $html = (string) $notification->toMail($this->alessa)->render();

    expect($notification->senderName($reply->fresh()))->toBe('Padmission')
        ->and($notification->senderName($question->fresh()))->toBe('Test Organization')
        ->and($html)->not->toContain('>Support<');
});

it('does not tell someone it was taken from them when they were never told it was handed to them', function (int $heldForSeconds, bool $told) {
    config(['padmission-tickets.notification-debounce' => 120]);
    $this->travelTo(now()->startOfSecond());
    $this->escalation->addTicketActivity(ActivityType::HandedOver, ActivitySender::System, $this->owner->id, ['from' => $this->owner->id, 'to' => $this->colleague->id]);
    $this->travel($heldForSeconds)->seconds();
    $this->escalation->addTicketActivity(ActivityType::HandedOver, ActivitySender::System, $this->third->id, ['from' => $this->colleague->id, 'to' => $this->third->id]);
    $this->escalation->update(['submitter_id' => $this->third->id]);

    $handedToColleague = new TicketHandedOverEvent($this->escalation, $this->owner, $this->owner->id, $this->colleague->id);
    $takenFromColleague = new TicketHandedOverEvent($this->escalation, $this->third, $this->colleague->id, $this->third->id);

    // Whichever of the two falls due first.
    expect((new TicketNotification($this->escalation, $takenFromColleague))->shouldSend($this->colleague))->toBe($told)
        ->and((new TicketNotification($this->escalation, $handedToColleague))->shouldSend($this->colleague))->toBeFalse();
})->with([
    'taken before the first email was due' => [30, false],
    'taken after it went out' => [121, true],
]);

it('tells whoever held the escalation from the start that it was taken from them', function () {
    $this->escalation->addTicketActivity(ActivityType::HandedOver, ActivitySender::System, $this->colleague->id, ['from' => $this->owner->id, 'to' => $this->colleague->id]);
    $this->escalation->update(['submitter_id' => $this->colleague->id]);

    expect((new TicketNotification($this->escalation, new TicketHandedOverEvent($this->escalation, $this->colleague, $this->owner->id, $this->colleague->id)))->shouldSend($this->owner))->toBeTrue();
});

it('says a ticket was assigned to you once in the subject', function () {
    $mail = (new TicketNotification($this->original, new TicketAssignedEvent($this->original, $this->owner)))->toMail($this->colleague);

    expect($mail->subject)->toBe("Ticket #{$this->original->id} assigned to you – {$this->original->subject}");
});
