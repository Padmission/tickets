<?php

namespace Padmission\Tickets\Http\DataMappers\StaffApi;

use Padmission\Tickets\Http\DataMappers\TicketAttachmentMapper;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketAttachment;

/*
 * A conversation entry for the staff API. A message carries the writer's HTML;
 * a history note (status changed, reassigned…) carries the panel's wording as
 * both HTML and plain text, so a client never has to rebuild it from `data`.
 */
class StaffActivityMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function map(TicketActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->type->value,
            'sender' => $activity->sender->value,
            'side' => $activity->side?->value,
            'is_own' => (bool) $activity->isOwn,
            'author' => $activity->user_id === null ? null : [
                'id' => $activity->user_id,
                'name' => $activity->userName,
            ],
            'html' => $activity->content,
            'text' => $activity->plainTextContent(),
            'attachments' => $activity->attachments
                ->map(fn (TicketAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->file_size,
                    ...TicketAttachmentMapper::map($attachment),
                ])
                ->values()
                ->all(),
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
