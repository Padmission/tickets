<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Tests\User;

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

it('requires create permission', function () {
    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

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
    $ticket = Ticket::factory()->create([
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
    $ticket = Ticket::factory()->create([
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
    createStorageMock();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create([
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
