<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    Storage::fake('s3');
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->requester = User::factory()->create();
    $this->supporter = User::factory()->create();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($this->supporter->id));

    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]);
});

function attachedTo(?ActivityType $type, ?User $createdBy = null, string $filepath = 'tickets/1/file.pdf'): TicketAttachment
{
    $activity = $type === null ? null : test()->ticket->ticketActivities()->create([
        'type' => $type,
        'sender' => $type === ActivityType::InternalMessage ? ActivitySender::Supporter : ActivitySender::User,
        'content' => '<p>x</p>',
    ]);

    return TicketAttachment::factory()->create([
        'ticket_id' => test()->ticket->id,
        'activity_id' => $activity?->id,
        'created_by' => $createdBy?->id,
        'filepath' => $filepath,
        'mime_type' => 'application/pdf',
    ]);
}

function askForUrl(TicketAttachment $attachment): TestResponse
{
    return test()->postJson(route('padmission-tickets::api.temporary-attachment-url', ['ticket' => test()->ticket]), ['filepath' => $attachment->filepath]);
}

it('refuses the requester a file on an internal note they cannot see', function () {
    $this->actingAs($this->requester);

    askForUrl(attachedTo(ActivityType::InternalMessage, $this->supporter))->assertNotFound();
});

it('gives the requester a file on a message they can see', function () {
    $this->actingAs($this->requester);

    askForUrl(attachedTo(ActivityType::Message, $this->requester))->assertOk();
});

it('gives support a file on an internal note', function () {
    $this->actingAs($this->supporter);

    askForUrl(attachedTo(ActivityType::InternalMessage, $this->supporter))->assertOk();
});

it('gives a file not yet sent only to whoever uploaded it', function () {
    $pending = attachedTo(null, $this->supporter, 'tickets/1/draft.pdf');

    $this->actingAs($this->requester);
    askForUrl($pending)->assertNotFound();

    $this->actingAs($this->supporter);
    askForUrl($pending)->assertOk();
});
