<?php

use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Tests\User;

it('is not sent to the person who just escalated, but still reaches the team it went to', function () {
    $owner = User::factory()->create();
    $padmission = User::factory()->create();
    (new TicketStatusSeeder)->run();
    $escalation = escalationFrom(attributes: ['submitter_id' => $owner->id, 'assignee_id' => $padmission->id]);
    Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

    $notification = new TicketNotification($escalation, new TicketCreatedEvent($escalation, $owner));

    expect($notification->shouldSend($owner))->toBeFalse()
        ->and($notification->shouldSend($padmission))->toBeTrue()
        ->and($notification->toMail($padmission)->viewData['intro'])->toBe('A new ticket has been created.');
});

it('acknowledges a requester\'s own ticket, naming who it is assigned to', function () {
    $requester = User::factory()->create();
    $supporter = User::factory()->create(['name' => 'Mike Shore']);
    $ticket = Ticket::factory()->create(['subject' => 'Rent is wrong', 'submitter_id' => $requester->id, 'assignee_id' => $supporter->id]);

    $notification = new TicketNotification($ticket, new TicketCreatedEvent($ticket, $requester));
    $mail = $notification->toMail($requester);
    $html = (string) $mail->render();

    expect($notification->shouldSend($requester))->toBeTrue()
        ->and($mail->subject)->toBe("New ticket #{$ticket->id} – Rent is wrong")
        ->and($html)->toContain('received your support request and will follow up as soon as we can.')
        ->and($html)->toContain('Assigned to:')
        ->and($html)->toContain('Mike Shore')
        ->and($html)->not->toContain('will be assigned shortly');
});

it('says a specialist will be assigned when nobody is yet', function () {
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->create(['submitter_id' => $requester->id, 'assignee_id' => null]);

    $html = (string) (new TicketNotification($ticket, new TicketCreatedEvent($ticket, $requester)))->toMail($requester)->render();

    expect($html)->toContain('A support specialist will be assigned shortly.')
        ->and($html)->not->toContain('Assigned to:');
});

it('leaves a reply already written to the requester for its own notification', function () {
    $requester = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create(['submitter_id' => $requester->id]);

    TicketActivity::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $requester->id, 'sender' => ActivitySender::User, 'type' => ActivityType::Message, 'content' => 'The rent is wrong.']);
    TicketActivity::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $requester->id, 'sender' => ActivitySender::System, 'type' => ActivityType::Message, 'content' => 'Thanks! We received your request.']);
    TicketActivity::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $supporter->id, 'sender' => ActivitySender::Supporter, 'type' => ActivityType::Message, 'content' => 'We are looking into it.']);

    $created = new TicketNotification($ticket, new TicketCreatedEvent($ticket, $requester));
    $created->toMail($requester);
    $bell = $created->toDatabase($requester);

    $reply = new TicketNotification($ticket, new TicketActivityEvent($ticket, ActivityType::Message, null, $supporter));

    expect($bell['body'])->toBe('Thanks! We received your request.')
        ->and($reply->shouldSend($requester))->toBeTrue()
        ->and($reply->toMail($requester)->viewData['activities']->pluck('content')->all())->toBe(['We are looking into it.']);
});
