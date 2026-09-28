<?php

use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;

test('system activity content falls back when structured data is missing', function (ActivityType $type): void {
    $activity = new TicketActivity([
        'sender' => ActivitySender::System,
        'type' => $type,
        'data' => null,
    ]);

    expect($activity->content)->toBeString()->not->toBeEmpty();
})->with([
    ActivityType::AssigneeChanged,
    ActivityType::TurnChanged,
    ActivityType::StatusChanged,
    ActivityType::PriorityChanged,
]);

test('an assignee change says who moved the ticket and from whom, reading the viewer as You', function (string $actor, string $viewer, string $expected): void {
    $people = [
        'breya' => User::factory()->create(['name' => 'Breya Birdsong']),
        'hoyt' => User::factory()->create(['name' => 'Hoyt Wyman']),
        'lead' => User::factory()->create(['name' => 'Team Lead']),
        'other' => User::factory()->create(['name' => 'Someone Else']),
    ];

    $activity = TicketActivity::factory()->create([
        'ticket_id' => Ticket::factory()->create()->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::AssigneeChanged,
        'user_id' => $actor === 'nobody' ? null : $people[$actor]->id,
        'data' => ['from' => $people['hoyt']->id, 'to' => $people['breya']->id],
    ]);

    $this->actingAs($people[$viewer]);

    expect($activity->content)->toBe($expected);
})->with([
    'taken, read by a colleague' => ['breya', 'other', 'Breya Birdsong took this ticket from Hoyt Wyman'],
    'taken, read by the taker' => ['breya', 'breya', 'You took this ticket from Hoyt Wyman'],
    'taken, read by the previous assignee' => ['breya', 'hoyt', 'Breya Birdsong took this ticket from you'],
    'handed, read by a colleague' => ['hoyt', 'other', 'Hoyt Wyman handed this ticket to Breya Birdsong'],
    'handed, read by the new assignee' => ['hoyt', 'breya', 'Hoyt Wyman handed this ticket to you'],
    'reassigned by a third person' => ['lead', 'other', 'Team Lead reassigned this ticket from Hoyt Wyman to Breya Birdsong'],
    'reassigned, read by the person who did it' => ['lead', 'lead', 'You reassigned this ticket from Hoyt Wyman to Breya Birdsong'],
    'reassigned by nobody known' => ['nobody', 'other', 'Reassigned from Hoyt Wyman to Breya Birdsong'],
]);

test('a first assignment keeps reading as an assignment, whoever reads it', function (): void {
    $assignee = User::factory()->create(['name' => 'Breya Birdsong']);

    $activity = TicketActivity::factory()->create([
        'ticket_id' => Ticket::factory()->create()->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::AssigneeChanged,
        'user_id' => $assignee->id,
        'data' => ['from' => null, 'to' => $assignee->id],
    ]);

    $this->actingAs($assignee);

    expect($activity->content)->toBe('Assigned to Breya Birdsong');
});

test('an assignee change escapes the names it shows, since history is rendered as HTML', function (): void {
    $from = User::factory()->create(['name' => '<b>Hoyt</b>']);
    $to = User::factory()->create(['name' => 'Breya']);

    $activity = TicketActivity::factory()->create([
        'ticket_id' => Ticket::factory()->create()->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::AssigneeChanged,
        'user_id' => $to->id,
        'data' => ['from' => $from->id, 'to' => $to->id],
    ]);

    expect($activity->content)->toBe('Breya took this ticket from &lt;b&gt;Hoyt&lt;/b&gt;');
});

test('reopen and hand over notes escape the names they show, since history is rendered as HTML', function (ActivityType $type, Closure $data, string $expected): void {
    $actor = User::factory()->create(['name' => '<img src=x onerror="a">']);
    $other = User::factory()->create(['name' => 'O\'Neil & "Co"']);

    $activity = TicketActivity::factory()->create([
        'ticket_id' => Ticket::factory()->create()->id,
        'sender' => ActivitySender::System,
        'type' => $type,
        'user_id' => $actor->id,
        'data' => $data($actor, $other),
    ]);

    expect($activity->content)->toBe($expected)
        ->not->toContain('<img');
})->with([
    'reopened' => [ActivityType::Reopened, fn (): array => [], 'Conversation reopened by &lt;img src=x onerror=&quot;a&quot;&gt;'],
    'handed over' => [ActivityType::HandedOver, fn (User $actor, User $other): array => ['from' => $actor->id, 'to' => $other->id], '&lt;img src=x onerror=&quot;a&quot;&gt; handed this escalation to O&#039;Neil &amp; &quot;Co&quot;'],
]);
