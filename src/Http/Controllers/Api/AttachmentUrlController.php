<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\TicketPlugin;
use Ramsey\Uuid\Uuid;

class AttachmentUrlController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, int $ticket): array
    {
        $this->authorize('create', TicketPlugin::resolveModelClass(Ticket::class));

        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'max:100'],
            'content_length' => ['required', 'int'],
            'thumbnail' => ['nullable', 'string'],
        ]);

        $id = Uuid::uuid4()->toString();
        $filename = $request->input('filename');
        $contentType = $request->input('content_type');
        $thumbnailData = $request->input('thumbnail');

        $filepath = 'tickets/'.$ticket.'/'.$id.'_'.$filename;
        $previewFilePath = null;

        if ($thumbnailData) {
            $previewFilePath = $this->storeThumbnail($thumbnailData, $ticket, $id);
        }

        ['url' => $url, 'headers' => $headers] = Storage::disk(config('padmission-tickets.attachments.disk'))->temporaryUploadUrl($filepath, now()->addMinutes(5), [
            'ContentType' => $contentType,
        ]);

        $attachment = TicketPlugin::resolveModelClass(TicketAttachment::class)::create([
            'ticket_id' => $ticket,
            'filename' => $filename,
            'filepath' => $filepath,
            'preview_filepath' => $previewFilePath,
            'mime_type' => $contentType,
            'file_size' => $request->integer('content_length'),
        ]);

        dispatch($this->pruneAttachments(...))->afterResponse();

        return [
            'attachment_id' => $attachment->id,
            'upload_url' => $url,
        ];
    }

    protected function storeThumbnail(string $thumbnailData, int $ticketId, string $fileId): ?string
    {
        $base64Data = str_replace('data:image/png;base64,', '', $thumbnailData);
        $thumbnailBinary = base64_decode($base64Data);

        if ($thumbnailBinary === false) {
            return null;
        }

        $thumbnailKey = 'tickets/'.$ticketId.'/thumbnails/'.$fileId.'.png';

        Storage::disk(config('padmission-tickets.attachments.preview_disk'))
            ->put($thumbnailKey, $thumbnailBinary, ['ContentType' => 'image/png']);

        return $thumbnailKey;
    }

    public function pruneAttachments(): void
    {
        $attachments = TicketPlugin::resolveModelClass(TicketAttachment::class)::query()
            ->whereNull('activity_id')
            ->where('created_at', '<', now()->subHour())
            ->get();

        $attachments->each->delete();
    }
}
