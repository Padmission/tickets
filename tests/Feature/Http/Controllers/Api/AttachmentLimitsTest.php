<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\StartTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Services\TicketStarter;
use Padmission\Tickets\Support\AttachmentName;
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
        $attachment = TicketAttachment::factory()->create(['ticket_id' => $this->ticket->id, 'activity_id' => null, 'created_by' => $this->user->id, 'filepath' => 'tickets/1/'.Str::uuid(), 'filename' => 'file', 'mime_type' => $mimeType]);

        $this->postJson(route('padmission-tickets::api.temporary-attachment-url', ['ticket' => $this->ticket]), ['filepath' => $attachment->filepath])->assertOk();

        return array_pop($this->signed);
    };

    expect($served('image/jpeg'))->toBe(['ResponseContentType' => 'image/jpeg'])
        ->and($served('video/mp4'))->toBe(['ResponseContentType' => 'video/mp4'])
        ->and($served('application/pdf'))->toBe(['ResponseContentType' => 'application/pdf'])
        ->and($served('application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->toBe([
            'ResponseContentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ResponseContentDisposition' => 'attachment; filename=file',
        ])
        ->and($served('text/plain'))->toBe(['ResponseContentType' => 'text/plain', 'ResponseContentDisposition' => 'attachment; filename=file'])
        ->and($served('text/html'))->toBe(['ResponseContentType' => 'application/octet-stream', 'ResponseContentDisposition' => 'attachment; filename=file']);
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
    'only dots' => ['..', 'file.jpg'],
    'a folder and dots' => ['photos/..', 'file.jpg'],
    'control characters' => ["sc\x00an\x1f.jpg", 'scan.jpg'],
    'dots inside a name' => ['rent..2026.jpg', 'rent..2026.jpg'],
]);

it('refuses a New ticket file whose name says no type', function () {
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
        ->assertHasActionErrors();

    expect(TicketAttachment::query()->exists())->toBeFalse();
});

it('tells the chat\'s file picker the types the server takes, with their usual extensions', function () {
    $accept = json_decode(ChatWidgetConfig::make()->allowFileUploads()->toJs(), true)['acceptedFileTypes'];

    expect(explode(',', $accept))
        ->toContain('application/pdf', '.pdf', 'image/jpeg', '.jpg', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '.docx', 'text/csv', '.csv')
        ->not->toContain('image/svg+xml', '.svg', 'text/html');

    config()->set('padmission-tickets.attachments.allowed_mime_types', ['application/zip']);

    expect(json_decode(ChatWidgetConfig::make()->toJs(), true)['acceptedFileTypes'])->toBe('application/zip,.zip');
});

it('shortens a long file name so its stored path fits, keeping its extension', function () {
    askToUpload(['filename' => str_repeat('a', 251).'.jpg'])->assertOk();

    $attachment = TicketAttachment::query()->latest('id')->sole();

    expect(mb_strlen($attachment->filepath))->toBeLessThanOrEqual(255)
        ->and($attachment->filename)->toEndWith('.jpg')
        ->and($attachment->filepath)->toEndWith('_'.$attachment->filename);
});

it('refuses a file whose name is not a type that can be attached, whatever type it claims', function (string $filename, string $type) {
    askToUpload(['filename' => $filename, 'content_type' => $type])->assertUnprocessable()->assertJsonValidationErrors('filename');

    expect(TicketAttachment::query()->exists())->toBeFalse();
})->with([
    'a program sent as text' => ['Payroll Q3.exe', 'text/plain'],
    'a script sent as a PDF' => ['run.sh', 'application/pdf'],
    'a page sent as an image' => ['page.html', 'image/png'],
    'a program behind a right-to-left override' => ["Payroll\u{202E}fdp.exe", 'application/pdf'],
]);

it('takes a name whose extension is an allowed type, even when the browser names another', function (string $filename, string $type) {
    askToUpload(['filename' => $filename, 'content_type' => $type])->assertOk();
})->with([
    'notes' => ['notes.txt', 'text/plain'],
    'a spreadsheet Windows calls Excel' => ['report.csv', 'application/vnd.ms-excel'],
    'an upper-case photo' => ['IMG_0001.JPG', 'image/jpeg'],
]);

it('gives a name without an extension its type\'s usual one', function () {
    askToUpload(['filename' => 'scan', 'content_type' => 'image/png'])->assertOk();

    expect(TicketAttachment::query()->sole()->filename)->toBe('scan.png');
});

it('drops invisible formatting characters from a name', function () {
    askToUpload(['filename' => "Pay\u{200B}roll\u{202E}.pdf", 'content_type' => 'application/pdf'])->assertOk();

    expect(TicketAttachment::query()->sole()->filename)->toBe('Payroll.pdf');
});

it('names a downloaded file by its own name', function () {
    $attachment = TicketAttachment::factory()->create(['ticket_id' => $this->ticket->id, 'activity_id' => null, 'created_by' => $this->user->id, 'filepath' => 'tickets/1/x_Rent "2026".docx', 'filename' => 'Rent "2026".docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

    $this->postJson(route('padmission-tickets::api.temporary-attachment-url', ['ticket' => $this->ticket]), ['filepath' => $attachment->filepath])->assertOk();

    expect(array_pop($this->signed)['ResponseContentDisposition'])->toBe('attachment; filename="Rent \"2026\".docx"');
});

it('refuses a program on a ticket started from the list, whatever its content looks like', function () {
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
            'attachments' => [UploadedFile::fake()->create('Payroll.exe', 1, 'text/plain')],
        ])
        ->assertHasActionErrors();

    expect(TicketAttachment::query()->exists())->toBeFalse();
});

// A long name whose last extension is no allowed type, hiding an allowed one and a program's further in.
function nameHidingACommand(): string
{
    return str_repeat('a', 204).'.cmd'.str_repeat('b', 43).'.pdf.'.str_repeat('c', 16);
}

it('never changes a long name\'s extension when shortening it', function () {
    expect(AttachmentName::safe(str_repeat('a', 300).'.pdf', 50))->toEndWith('.pdf')->toHaveLength(50)
        ->and(AttachmentName::safe(nameHidingACommand(), 255))->toEndWith('.'.str_repeat('c', 16))
        ->and(AttachmentName::safe(nameHidingACommand(), 120))->toEndWith('.'.str_repeat('c', 16))
        ->and(AttachmentName::safe('a.pdf.'.str_repeat('c', 300), 50))->toBe('file');
});

it('refuses a long name hiding a command behind a shortened extension, on New ticket', function () {
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
            'attachments' => [UploadedFile::fake()->create(nameHidingACommand(), 1, 'text/plain')],
        ])
        ->assertHasActionErrors();

    expect(TicketAttachment::query()->exists())->toBeFalse()
        ->and(Ticket::query()->where('subject', 'Pay stubs')->exists())->toBeFalse();
});

it('refuses it through the chat too', function () {
    askToUpload(['filename' => nameHidingACommand(), 'content_type' => 'text/plain'])->assertUnprocessable()->assertJsonValidationErrors('filename');
});

it('checks each file\'s name as stored before New ticket writes it, leaving no ticket behind', function () {
    (new TicketPrioritySeeder)->run();
    Storage::fake(config('padmission-tickets.attachments.disk'));
    $requester = User::factory()->create();

    expect(fn () => resolve(TicketStarter::class)->openFor($requester, 'Pay stubs', '<p>Hello</p>', null, [UploadedFile::fake()->create('run.cmd', 1, 'text/plain')]))
        ->toThrow(ValidationException::class);

    expect(Ticket::query()->where('subject', 'Pay stubs')->exists())->toBeFalse()
        ->and(TicketAttachment::query()->exists())->toBeFalse();
});
