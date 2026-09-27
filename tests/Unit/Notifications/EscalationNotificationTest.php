<?php

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    Queue::fake();
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->owner = User::factory()->create(['name' => 'Test Admin']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->aisha = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->felix = User::factory()->create(['name' => 'Felix Moreno']);
    $this->padmission = User::factory()->create(['name' => 'Kevin McKee']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->owner->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Padmission');
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->padmission->id));

    $this->escalation = escalationFrom(attributes: [
        'subject' => 'Recert rent is wrong',
        'submitter_id' => $this->owner->id,
        'assignee_id' => $this->padmission->id,
    ]);
    $this->original = Ticket::factory()->open()->create([
        'linked_ticket_id' => $this->escalation->id,
        'submitter_id' => $this->aisha->id,
        'assignee_id' => $this->owner->id,
    ]);
});

function padmissionReply(Ticket $escalation, User $padmission, string $content = 'Which household?'): TicketActivity
{
    return TicketActivity::factory()->create([
        'ticket_id' => $escalation->id,
        'user_id' => $padmission->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => $content,
    ]);
}

function wordingFor(Ticket $ticket, object $event, User $recipient): array
{
    return invade(new TicketNotification($ticket, $event))->wording($recipient);
}

it('tells the owner the team replied on their escalation, and links the escalation with its original beside it', function () {
    padmissionReply($this->escalation, $this->padmission);
    $notification = new TicketNotification($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message, null, $this->padmission));
    $id = $this->escalation->id;

    $mail = $notification->toMail($this->owner);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe("Padmission replied on your escalation #{$id} – Recert rent is wrong")
        ->and($mail->viewData['headline'])->toBe('Reply on your escalation')
        ->and($mail->viewData['intro'])->toBe("Padmission replied on your escalation about Aisha Brooks's ticket. Answer Padmission on the escalation, or pass the answer on to the requester on their own ticket.")
        ->and($mail->viewData['actionUrl'])->toBe(url("/test/tickets/{$id}/view?linked={$this->original->id}"))
        ->and($html)->toContain('Open the escalation')
        ->and($html)->toContain('Reply on your escalation');
});

it('names the team for its messages when their writer cannot be found, as on a queue worker', function () {
    padmissionReply($this->escalation, $this->padmission)->forceFill(['user_id' => null])->save();
    $notification = new TicketNotification($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message));

    $html = (string) $notification->toMail($this->owner)->render();

    expect($html)->toContain('Padmission')
        ->and($html)->not->toMatch('/>\s*Support\s*</');
});

it('tells the owner only of a reply, not of the team\'s own notes such as closing it', function () {
    TicketActivity::factory()->create([
        'ticket_id' => $this->escalation->id,
        'user_id' => $this->padmission->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::Closed,
    ]);
    $event = new TicketActivityEvent($this->escalation, ActivityType::Closed, null, $this->padmission);

    expect((new TicketNotification($this->escalation, $event))->shouldSend($this->owner))->toBeFalse()
        ->and((new TicketNotification($this->escalation, $event))->shouldSend($this->padmission))->toBeTrue();

    padmissionReply($this->escalation, $this->padmission);

    expect((new TicketNotification($this->escalation, $event))->shouldSend($this->owner))->toBeTrue();
});

it('gives the bell the same subject and link as the relay email', function () {
    padmissionReply($this->escalation, $this->padmission);
    $notification = new TicketNotification($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message, null, $this->padmission));

    $bell = $notification->toDatabase($this->owner);

    expect($bell['title'])->toBe("Padmission replied on your escalation #{$this->escalation->id} – Recert rent is wrong")
        ->and($bell['actions'][0]['label'])->toBe('Open the escalation')
        ->and($bell['actions'][0]['url'])->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->original->id}"));
});

it('describes the other team without a name when the receiving panel has none', function () {
    TicketPlugin::get('test2')->supportTeamName(null);

    $wording = wordingFor($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message), $this->owner);

    expect($wording['subject'])->toBe("The team you escalated to replied on your escalation #{$this->escalation->id} – Recert rent is wrong")
        ->and($wording['intro'])->toBe("The team you escalated to replied on your escalation about Aisha Brooks's ticket. Answer them on the escalation, or pass the answer on to the requester on their own ticket.");
});

it('keeps the ordinary wording for the team the escalation went to', function () {
    $wording = wordingFor($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message), $this->padmission);

    expect($wording['subject'])->toBe("Ticket updated #{$this->escalation->id} – Recert rent is wrong")
        ->and($wording['actionUrl'])->toBe(url("/test2/tickets/{$this->escalation->id}/view"));
});

it('keeps the ordinary wording on an ordinary ticket', function () {
    $wording = wordingFor($this->original, new TicketActivityEvent($this->original, ActivityType::Message), $this->aisha);

    expect($wording['subject'])->toBe("Ticket updated #{$this->original->id} – {$this->original->subject}")
        ->and($wording['headline'])->toBe('Recent Ticket Activity');
});

it('tells the owner the team closed their escalation and opens the one original still open', function () {
    padmissionReply($this->escalation, $this->padmission, 'The allowance table is fixed.');
    $this->escalation->close(closedById: $this->padmission->id);
    $id = $this->escalation->id;

    $mail = (new TicketNotification($this->escalation->refresh(), new TicketClosedEvent($this->escalation, $this->padmission)))->toMail($this->owner);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe("Padmission closed your escalation #{$id} – Recert rent is wrong")
        ->and($mail->viewData['headline'])->toBe('Escalation closed')
        ->and($mail->viewData['intro'])->toBe("Padmission closed your escalation about Aisha Brooks's ticket. Aisha Brooks's ticket is still open. Update them and close it when they're done.")
        ->and($mail->viewData['actionUrl'])->toBe(url("/test/tickets/{$this->original->id}/view?linked={$id}"))
        ->and($html)->toContain('Latest reply from Padmission:')
        ->and($html)->toContain('The allowance table is fixed.')
        ->and($mail->viewData['actionLabel'])->toBe("Open Aisha Brooks's ticket")
        ->and($html)->toContain('Open Aisha Brooks');
});

it('counts the originals still open when the team closes an escalation about several', function () {
    $felixTicket = Ticket::factory()->open()->create(['linked_ticket_id' => $this->escalation->id, 'submitter_id' => $this->felix->id]);
    $this->escalation->close(closedById: $this->padmission->id);

    $wording = wordingFor($this->escalation->refresh(), new TicketClosedEvent($this->escalation), $this->owner);

    expect($wording['intro'])->toBe("Padmission closed your escalation about Aisha Brooks's and Felix Moreno's tickets. 2 of its tickets are still open. Update the requesters and close each one when they're done.")
        ->and($wording['actionLabel'])->toBe('Open the escalation')
        ->and($wording['actionUrl'])->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->original->id}"))
        ->and($felixTicket->exists)->toBeTrue();
});

it('only names the originals once they are all closed', function () {
    $this->original->close(closedById: $this->owner->id);
    $this->escalation->close(closedById: $this->padmission->id);

    $wording = wordingFor($this->escalation->refresh(), new TicketClosedEvent($this->escalation), $this->owner);

    expect($wording['intro'])->toBe("Padmission closed your escalation about Aisha Brooks's ticket.")
        ->and($wording['actionLabel'])->toBe('Open the escalation')
        ->and($wording['actionUrl'])->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->original->id}"));
});

it('tells the requester of an ordinary ticket who replied last from support', function () {
    TicketActivity::factory()->create([
        'ticket_id' => $this->original->id,
        'user_id' => $this->owner->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => 'Fixed on our side.',
    ]);
    $this->original->close(closedById: $this->owner->id);

    $mail = (new TicketNotification($this->original->refresh(), new TicketClosedEvent($this->original)))->toMail($this->aisha);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe("Ticket closed #{$this->original->id} – {$this->original->subject}")
        ->and($html)->toContain('Your ticket has been closed.')
        ->and($html)->toContain('Latest reply from support:')
        ->and($html)->toContain('Fixed on our side.');
});

it('tells the new owner the escalation was handed to them', function () {
    $this->escalation->update(['submitter_id' => $this->colleague->id]);
    $event = new TicketHandedOverEvent($this->escalation, $this->owner, $this->owner->id, $this->colleague->id);
    $id = $this->escalation->id;

    $mail = (new TicketNotification($this->escalation, $event))->toMail($this->colleague);

    expect($mail->subject)->toBe("Escalation handed to you #{$id} – Recert rent is wrong")
        ->and($mail->viewData['headline'])->toBe('Escalation handed to you')
        ->and($mail->viewData['intro'])->toBe("Test Admin handed you the escalation to Padmission about Aisha Brooks's ticket. Padmission's replies now come to you.")
        ->and($mail->viewData['actionLabel'])->toBe('Open the escalation')
        ->and($mail->viewData['actionUrl'])->toBe(url("/test/tickets/{$id}/view?linked={$this->original->id}"));
});

it('tells the previous owner the escalation was taken over and links a ticket they can still open', function () {
    $this->escalation->update(['submitter_id' => $this->colleague->id]);
    $event = new TicketHandedOverEvent($this->escalation, $this->colleague, $this->owner->id, $this->colleague->id);
    $notification = new TicketNotification($this->escalation, $event);

    $mail = $notification->toMail($this->owner);
    $bell = $notification->toDatabase($this->owner);

    expect($mail->subject)->toBe("Escalation taken over #{$this->escalation->id} – Recert rent is wrong")
        ->and($mail->viewData['headline'])->toBe('Escalation taken over')
        ->and($mail->viewData['intro'])->toBe("Maria Lopez took over the escalation to Padmission about Aisha Brooks's ticket. Padmission's replies now go to them.")
        ->and($mail->viewData['actionLabel'])->toBe("Open Aisha Brooks's ticket")
        ->and($mail->viewData['actionUrl'])->toBe(url("/test/tickets/{$this->original->id}/view"))
        ->and($bell['title'])->toBe($mail->subject)
        ->and($bell['body'])->toBe($mail->viewData['intro'])
        ->and($bell['actions'][0]['url'])->toBe($mail->viewData['actionUrl']);
});

it('sends the previous owner to their team\'s escalations when no original is open', function () {
    $this->original->close(closedById: $this->owner->id);
    $this->escalation->update(['submitter_id' => $this->colleague->id]);
    $event = new TicketHandedOverEvent($this->escalation, $this->colleague, $this->owner->id, $this->colleague->id);

    $wording = wordingFor($this->escalation, $event, $this->owner);

    expect($wording['actionLabel'])->toBe("View your team's escalations")
        ->and($wording['actionUrl'])->toBe(url('/test/tickets?tab=linked'));
});

it('names the originals and their requesters past host scopes, leaving out deleted originals and other tenants', function () {
    config()->set('padmission-tickets.tenancy.enabled', true);
    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Ticket::query()->whereKey([$this->escalation->id, $this->original->id])->update(['tenant_id' => 1]);
    Ticket::factory()->open()->create(['linked_ticket_id' => $this->escalation->id, 'submitter_id' => $this->felix->id])->delete();
    Ticket::factory()->open()->create(['linked_ticket_id' => $this->escalation->id, 'submitter_id' => $this->felix->id, 'tenant_id' => 2]);

    Ticket::addGlobalScope('host', fn ($query) => $query->whereRaw('1 = 0'));
    User::addGlobalScope('host', fn ($query) => $query->whereRaw('1 = 0'));

    try {
        $wording = wordingFor($this->escalation->refresh(), new TicketActivityEvent($this->escalation, ActivityType::Message), $this->owner);
    } finally {
        Ticket::clearBootedModels();
        User::clearBootedModels();
    }

    expect($wording['intro'])->toContain("about Aisha Brooks's ticket.");
});

it('links the escalation page even where the receiving panel has no tickets plugin', function () {
    // As on a queue worker that leaves the receiving panel's plugins out, under a host policy that lets its staff in.
    invade(Filament::getPanel('test2'))->plugins = [];
    Gate::policy(Ticket::class, TestTicketPolicy::class);
    padmissionReply($this->escalation, $this->padmission);

    $owner = wordingFor($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message), $this->owner);
    $padmission = wordingFor($this->escalation, new TicketActivityEvent($this->escalation, ActivityType::Message), $this->padmission);

    expect($owner['actionUrl'])->toBe(url("/test/tickets/{$this->escalation->id}/view?linked={$this->original->id}"))
        ->and($owner['subject'])->toStartWith('The team you escalated to replied on your escalation')
        ->and($padmission['actionUrl'])->toBe(url("/test2/tickets/{$this->escalation->id}/view"));
});

it('shows the previous owner this hand over and the unread messages, not earlier hand overs', function () {
    TicketActivity::factory()->create(['ticket_id' => $this->escalation->id, 'user_id' => $this->owner->id, 'sender' => ActivitySender::System, 'type' => ActivityType::HandedOver, 'data' => ['from' => $this->owner->id, 'to' => $this->colleague->id]]);
    TicketActivity::factory()->create(['ticket_id' => $this->escalation->id, 'user_id' => $this->owner->id, 'sender' => ActivitySender::System, 'type' => ActivityType::HandedOver, 'data' => ['from' => $this->colleague->id, 'to' => $this->owner->id]]);
    padmissionReply($this->escalation, $this->padmission, 'Which household?');
    $line = TicketActivity::factory()->create(['ticket_id' => $this->escalation->id, 'user_id' => $this->owner->id, 'sender' => ActivitySender::System, 'type' => ActivityType::HandedOver, 'data' => ['from' => $this->owner->id, 'to' => $this->colleague->id]]);
    $this->escalation->update(['submitter_id' => $this->colleague->id]);

    $mail = (new TicketNotification($this->escalation, new TicketHandedOverEvent($this->escalation, $this->owner, $this->owner->id, $this->colleague->id)))->toMail($this->colleague);

    expect($mail->viewData['activities']->pluck('id')->all())->toBe([
        $this->escalation->ticketActivities()->where('content', 'Which household?')->value('id'),
        $line->id,
    ]);
});
