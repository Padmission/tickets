<?php

namespace Padmission\Tickets\Http\DataMappers;

use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketAttachment;

class TicketActivityMapper
{
    public static function map(TicketActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'side' => $activity->side,
            'user_name' => $activity->userName,
            'content' => $activity->content,
            'attachments' => $activity->attachments->map(fn (TicketAttachment $attachment) => TicketAttachmentMapper::map($attachment)),
            'created_at' => $activity->created_at,
        ];
    }
}
