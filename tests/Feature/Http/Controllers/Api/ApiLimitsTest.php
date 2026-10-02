<?php

use Illuminate\Testing\TestResponse;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    $this->user = $this->login();
    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->user->id]);
});

function send(string $content): TestResponse
{
    return test()->postJson(route('padmission-tickets::api.messages.store', ['ticket' => test()->ticket]), ['content' => $content]);
}

it('refuses a message longer than the configured limit', function () {
    config()->set('padmission-tickets.api.max_message_length', 20);

    send(str_repeat('a', 20))->assertOk();
    send(str_repeat('a', 21))->assertUnprocessable()->assertJsonValidationErrors('content');

    expect($this->ticket->ticketActivities()->where('type', ActivityType::Message)->where('sender', '!=', 'system')->count())->toBe(1);
});

it('keeps messages to a length the content column holds by default', function () {
    send(str_repeat('a', 16000))->assertOk();
    send(str_repeat('a', 16001))->assertUnprocessable()->assertJsonValidationErrors('content');
});

it('limits how often one person writes through the API', function () {
    config()->set('padmission-tickets.api.writes_per_minute', 3);

    foreach (range(1, 3) as $attempt) {
        send("<p>Message {$attempt}</p>")->assertOk();
    }

    send('<p>One too many</p>')->assertTooManyRequests();
    $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Another'])->assertTooManyRequests();

    $this->actingAs($other = User::factory()->create());
    $this->ticket->update(['submitter_id' => $other->id]);

    send('<p>Someone else</p>')->assertOk();
});

it('leaves reading alone', function () {
    config()->set('padmission-tickets.api.writes_per_minute', 1);

    send('<p>Hi</p>')->assertOk();

    foreach (range(1, 5) as $attempt) {
        $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))->assertOk();
    }
});

it('refuses a message whose cleaned HTML would not fit its column, rather than failing to save it', function () {
    send(str_repeat('"', 16000))->assertUnprocessable()->assertJsonValidationErrors('content');
    send(str_repeat('&', 16000))->assertUnprocessable()->assertJsonValidationErrors('content');

    expect($this->ticket->ticketActivities()->where('type', ActivityType::Message)->where('sender', '!=', 'system')->exists())->toBeFalse();

    send(str_repeat('"', 10000))->assertOk();
});
