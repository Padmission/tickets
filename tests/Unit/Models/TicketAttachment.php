<?php

use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\TicketAttachment;

it('deletes file when record gets deleted', function () {
    $filesystem = Storage::fake(config('padmission-tickets.attachments.disk'));

    $attachment = TicketAttachment::factory()->create();
    $filesystem->put($attachment->filepath, 'testfile');

    $filesystem->assertExists($attachment->filepath);

    $attachment->delete();

    $filesystem->assertMissing($attachment->filepath);
});
