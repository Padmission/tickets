<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\StartTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * A presigned upload signs neither the type nor the length the file is sent
 * with, so the limits hold where the server decides: the type and size asked
 * for, the size actually stored when the message is sent, and the type a file
 * is served as.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();

    $this->user = $this->login();
    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->user->id]);
    $this->signed = [];

    $mock = Mockery::mock(Storage::fake('s3'))->makePartial();
    $mock->shouldReceive('temporaryUploadUrl')->andReturn(['url' => 'https://upload.example', 'headers' => []]);
    $mock->shouldReceive('temporaryUrl')->andReturnUsing(function (string $path, $expiration, array $options = []) {
        $this->signed[] = $options;

        return 'https://download.example';
    });
    Storage::set('s3', $mock);
});

function askToUpload(array $data): TestResponse
{
    return test()->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => test()->ticket]), [
        'filename' => 'file',
        'content_type' => 'image/jpeg',
        'content_length' => 1024,
        ...$data,
    ]);
}

it('refuses a type a browser would run', function (string $type) {
    askToUpload(['filename' => 'page', 'content_type' => $type])->assertUnprocessable()->assertJsonValidationErrors('content_type');

    expect(TicketAttachment::query()->exists())->toBeFalse();
})->with(['text/html', 'image/svg+xml', 'application/xhtml+xml', 'text/javascript', 'application/javascript', 'application/x-msdownload', 'application/x-sh', 'application/octet-stream']);

it('takes images, videos, PDFs and everyday documents', function (string $type) {
    askToUpload(['content_type' => $type])->assertOk();
})->with([
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/heic',
    'video/mp4',
    'video/quicktime',
    'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    'rtf' => 'application/rtf',
    'csv' => 'text/csv',
    'txt' => 'text/plain',
]);

it('lets the host choose the types', function () {
    config()->set('padmission-tickets.attachments.allowed_mime_types', ['application/zip']);

    askToUpload(['content_type' => 'application/zip'])->assertOk();
    askToUpload(['content_type' => 'image/jpeg'])->assertUnprocessable();
});

it('refuses a file larger than the chat allows, as the panel sets it', function () {
    askToUpload(['content_length' => 10 * 1024 * 1024])->assertOk();
    askToUpload(['content_length' => 10 * 1024 * 1024 + 1])->assertUnprocessable()->assertJsonValidationErrors('content_length');

    TicketPlugin::get()->showChatWidget(config: ChatWidgetConfig::make()->allowFileUploads(maxFileSize: 2048));

    askToUpload(['content_length' => 2048])->assertOk();
    askToUpload(['content_length' => 2049])->assertUnprocessable()->assertJsonValidationErrors('content_length');
    askToUpload(['content_length' => 0])->assertUnprocessable()->assertJsonValidationErrors('content_length');
});

it('refuses an oversized preview image', function () {
    askToUpload(['thumbnail' => 'data:image/png;base64,'.str_repeat('A', 2 * 1024 * 1024)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('thumbnail');
});

it('opens an image, video or PDF in the browser, downloads a document, and downloads anything else as unknown bytes', function () {
    $served = function (string $mimeType): array {
        $attachment = TicketAttachment::factory()->create(['ticket_id' => $this->ticket->id, 'activity_id' => null, 'created_by' => $this->user->id, 'filepath' => 'tickets/1/'.Str::uuid(), 'mime_type' => $mimeType]);

        $this->postJson(route('padmission-tickets::api.temporary-attachment-url', ['ticket' => $this->ticket]), ['filepath' => $attachment->filepath])->assertOk();

        return array_pop($this->signed);
    };

    expect($served('image/jpeg'))->toBe(['ResponseContentType' => 'image/jpeg'])
        ->and($served('video/mp4'))->toBe(['ResponseContentType' => 'video/mp4'])
        ->and($served('application/pdf'))->toBe(['ResponseContentType' => 'application/pdf'])
        ->and($served('application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->toBe([
            'ResponseContentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ResponseContentDisposition' => 'attachment',
        ])
        ->and($served('text/plain'))->toBe(['ResponseContentType' => 'text/plain', 'ResponseContentDisposition' => 'attachment'])
        ->and($served('text/html'))->toBe(['ResponseContentType' => 'application/octet-stream', 'ResponseContentDisposition' => 'attachment']);
});

it('refuses those types on a ticket started from the list too', function () {
    (new TicketPrioritySeeder)->run();
    TicketPlugin::get()->showChatWidget(config: ChatWidgetConfig::make()->allowFileUploads());
    $requester = User::factory()->create();

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make(StartTicketAction::class), [
            'kind' => StartTicketAction::ORGANIZATION,
            'requester_id' => $requester->id,
            'assign' => 'me',
            'subject' => 'Pay stubs',
            'message' => '<p>Hello</p>',
            'attachments' => [UploadedFile::fake()->createWithContent('page.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
        ])
        ->assertHasActionErrors();

    expect(Ticket::query()->where('subject', 'Pay stubs')->exists())->toBeFalse();
});

it('keeps a file name to its last part, so it can never point elsewhere on the disk', function (string $sent, string $kept) {
    askToUpload(['filename' => $sent])->assertOk();

    $attachment = TicketAttachment::query()->latest('id')->sole();

    expect($attachment->filename)->toBe($kept)
        ->and($attachment->filepath)->toMatch('#^tickets/'.$this->ticket->id.'/[0-9a-f-]{36}_'.preg_quote($kept, '#').'$#')
        ->and(explode('/', $attachment->filepath))->toHaveCount(3)->not->toContain('..');
})->with([
    'parent folders' => ['../../../evil.jpg', 'evil.jpg'],
    'folders' => ['photos/2026/scan.jpg', 'scan.jpg'],
    'Windows folders' => ['C:\\Users\\Ann\\scan.jpg', 'scan.jpg'],
    'only dots' => ['..', 'file'],
    'a folder and dots' => ['photos/..', 'file'],
    'control characters' => ["sc\x00an\x1f.jpg", 'scan.jpg'],
    'dots inside a name' => ['rent..2026.jpg', 'rent..2026.jpg'],
]);

it('keeps a name from New ticket to its last part too', function () {
    (new TicketPrioritySeeder)->run();
    Storage::fake(config('padmission-tickets.attachments.disk'));
    TicketPlugin::get()->showChatWidget(config: ChatWidgetConfig::make()->allowFileUploads());
    $requester = User::factory()->create();

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make(StartTicketAction::class), [
            'kind' => StartTicketAction::ORGANIZATION,
            'requester_id' => $requester->id,
            'assign' => 'me',
            'subject' => 'Pay stubs',
            'message' => '<p>Hello</p>',
            'attachments' => [UploadedFile::fake()->create('..', 1, 'application/pdf')],
        ])
        ->assertHasNoActionErrors();

    expect(TicketAttachment::query()->sole())
        ->filename->toBe('file')
        ->filepath->not->toEndWith('..');
});
