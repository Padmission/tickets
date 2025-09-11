<?php

namespace Padmission\Tickets\Http\DataMappers;

use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\TicketAttachment;

class TicketAttachmentMapper
{
    public static function map(TicketAttachment $attachment): array
    {
        return [
            'filename' => $attachment->filename,
            'url' => Storage::disk(config('padmission-tickets.attachments.disk'))->temporaryUrl($attachment->filepath, now()->addMinutes(5)),
            'preview_url' => $attachment->preview_filepath
                ? Storage::disk(config('padmission-tickets.attachments.preview_disk'))->temporaryUrl($attachment->preview_filepath, now()->addMinutes(5))
                : null,
            'type' => match (true) {
                str_starts_with($attachment->mime_type, 'image/') => 'image',
                str_starts_with($attachment->mime_type, 'video/') => 'video',
                default => 'file'
            },
        ];
    }
}
