<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
});

function createStorageMock()
{
    $fake = Storage::fake('s3');
    $mock = Mockery::mock($fake)->makePartial();

    $mock
        ->shouldReceive('temporaryUploadUrl')
        ->andReturnUsing(function (string $path, $expiration, array $options = []) {
            return [
                'url' => 'https://mocked-temporary-url.com',
                'headers' => [],
            ];
        });

    Storage::set('s3', $mock);
}

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->postJson(route('padmission-tickets::api.attachment-url', [
            'ticket' => $ticket,
        ]))
        ->assertUnauthorized();
});

it('requires create permission from the requester', function () {
    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    Gate::before(fn (User $authUser, string $ability) => $ability === 'create' ? false : null);

    $this->actingAs($user);

    $this
        ->postJson(
            route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]),
            [
                'filename' => 'test.jpg',
                'content_type' => 'image/jpeg',
                'content_length' => '1024',
            ]
        )
        ->assertForbidden();
});

it('generates a temporary url', function () {
    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'submitter_id' => $user->id,
    ]);

    $this->actingAs($user);

    $resp = $this
        ->postJson(
            route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]),
            [
                'filename' => 'test.jpg',
                'content_type' => 'image/jpeg',
                'content_length' => '1024',
            ]
        )
        ->assertOk();

    $this->assertDatabaseCount(TicketAttachment::class, 1);

    expect($resp->getData())
        ->toHaveKeys(['upload_url', 'attachment_id'])
        ->upload_url->toBe('https://mocked-temporary-url.com');
});

it('accepts thumbnails', function () {
    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'submitter_id' => $user->id,
    ]);

    $this->actingAs($user);

    $this
        ->postJson(
            route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]),
            [
                'filename' => 'test.jpg',
                'content_type' => 'image/jpeg',
                'content_length' => '1024',
                'thumbnail' => 'data:image/png;base64,'.base64_encode('some_data'),
            ]
        )
        ->assertOk();

    Storage::disk('s3')->assertCount("/tickets/{$ticket->id}/thumbnails/", 1);
});

it('prunes expired attachments without an activity', function () {
    // The prune runs after the response with its own now(), so a second ticking over mid-request would expire the hour-old attachment.
    $this->freezeSecond();

    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'submitter_id' => $user->id,
    ]);

    TicketAttachment::factory()
        ->count(2)
        ->sequence(
            ['created_at' => now()->subDay()],
            ['created_at' => now()->subHour()]
        )
        ->create([
            'activity_id' => null,
        ]);

    $this->actingAs($user);

    $this
        ->postJson(
            route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]),
            [
                'filename' => 'test.jpg',
                'content_type' => 'image/jpeg',
                'content_length' => '1024',
                'thumbnail' => 'data:image/png;base64,'.base64_encode('some_data'),
            ]
        )
        ->assertOk();

    expect(TicketAttachment::query()->count())->toBe(2);
});

it('forbids issuing upload urls for a ticket the user cannot access', function () {
    createStorageMock();

    Gate::before(fn (User $authUser, string $ability) => $ability === 'manage' ? false : null);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user);

    $this
        ->postJson(
            route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]),
            [
                'filename' => 'test.jpg',
                'content_type' => 'image/jpeg',
                'content_length' => '1024',
            ]
        )
        ->assertForbidden();

    $this->assertDatabaseCount(TicketAttachment::class, 0);
});

it('records the creator on new attachments', function () {
    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    $this->actingAs($user);

    $resp = $this
        ->postJson(
            route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]),
            [
                'filename' => 'test.jpg',
                'content_type' => 'image/jpeg',
                'content_length' => '1024',
            ]
        )
        ->assertOk();

    $attachment = TicketAttachment::query()->findOrFail($resp->json('attachment_id'));

    expect($attachment->created_by)->toBe($user->id)
        ->and($attachment->ticket_id)->toBe($ticket->id);
});

it('gives a supporter left out of the chat widget an upload url for their reply', function () {
    createStorageMock();

    Gate::before(fn (User $authUser, string $ability) => $ability === 'create' ? false : null);

    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->open()->create();

    $this
        ->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]), [
            'filename' => 'test.jpg',
            'content_type' => 'image/jpeg',
            'content_length' => '1024',
        ])
        ->assertOk();
});

it('refuses upload urls to someone who may read a ticket but not reply on it', function () {
    createStorageMock();
    Gate::policy(Ticket::class, ReadsButMayNotReplyPolicy::class);

    $this->actingAs(User::factory()->create());
    $original = Ticket::factory()->open()->create();

    $this
        ->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => $original]), [
            'filename' => 'test.jpg',
            'content_type' => 'image/jpeg',
            'content_length' => '1024',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount(TicketAttachment::class, 0);
});

it('refuses upload urls on a closed ticket', function () {
    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->closed()->create(['submitter_id' => $user->id]);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => $ticket]), [
            'filename' => 'test.jpg',
            'content_type' => 'image/jpeg',
            'content_length' => '1024',
        ])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'This ticket is already closed.']);

    $this->assertDatabaseCount(TicketAttachment::class, 0);
});

class ReadsButMayNotReplyPolicy extends TestTicketPolicy
{
    public function reply(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
