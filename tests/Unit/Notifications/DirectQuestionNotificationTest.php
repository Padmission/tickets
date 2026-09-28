<?php

use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    Queue::fake();
    (new TicketStatusSeeder)->run();
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $this->owner = User::factory()->create(['name' => 'Tess Support']);
    $this->staff = User::factory()->create(['name' => 'Mike Shore']);

    $this->question = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->owner->id]);
    $this->question->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System, $this->owner->id);
});

it('tells the owner the other team replied on their question, about no one\'s ticket', function () {
    $this->question->ticketActivities()->create(['type' => ActivityType::Message, 'sender' => ActivitySender::Supporter, 'user_id' => $this->staff->id, 'content' => 'Loaded.']);

    $mail = (new TicketNotification($this->question, new TicketActivityEvent($this->question, ActivityType::Message, null, $this->staff)))->toMail($this->owner);

    expect($mail->viewData['intro'])->toBe('Platform Support replied on your question.');
});

it('tells the owner the other team closed their question', function () {
    $mail = (new TicketNotification($this->question, new TicketClosedEvent($this->question, $this->staff)))->toMail($this->owner);

    expect($mail->viewData['intro'])->toBe('Platform Support closed your question.');
});

it('tells a colleague the question was handed to them, and its owner that it was taken', function () {
    $colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->question->update(['submitter_id' => $colleague->id]);

    $handedTo = (new TicketNotification($this->question, new TicketHandedOverEvent($this->question, $this->owner, $this->owner->id, $colleague->id)))->toMail($colleague);
    $takenFrom = (new TicketNotification($this->question, new TicketHandedOverEvent($this->question, $colleague, $this->owner->id, $colleague->id)))->toMail($this->owner);

    expect($handedTo->viewData['intro'])->toBe('Tess Support handed you the question to Platform Support. Platform Support\'s replies now come to you.')
        ->and($takenFrom->viewData['intro'])->toBe('Maria Lopez took over the question to Platform Support. Platform Support\'s replies now go to them.');
});

it('reads as an escalation about its tickets once an original joins it', function () {
    $original = Ticket::factory()->open()->create(['submitter_id' => User::factory()->create(['name' => 'Nina Patel'])->id]);
    $this->question->forgetIsEscalation();
    $original->update(['linked_ticket_id' => $this->question->id]);
    $this->question->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, $this->owner->id, ['original' => $original->id]);

    $mail = (new TicketNotification($this->question->fresh(), new TicketClosedEvent($this->question, $this->staff)))->toMail($this->owner);

    expect($mail->viewData['intro'])->toContain('closed your escalation about Nina Patel\'s ticket');
});
