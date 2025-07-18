<?php

namespace Padmission\Tickets\Models\Observers;

use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\TicketAttachment;

class TicketAttachmentObserver
{
    public function deleting(TicketAttachment $attachment): void
    {
        Storage::disk(config('padmission-tickets.attachments.disk'))->delete($attachment->filepath);
    }
}
