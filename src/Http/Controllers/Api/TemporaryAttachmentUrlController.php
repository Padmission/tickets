<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TemporaryAttachmentUrlController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, int $ticket): array
    {
        $this->authorize('create', TicketPlugin::resolveModelClass(Ticket::class));

        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
        ]);

        $filename = $request->input('filename');

        return [
            'url' => Storage::disk(config('padmission-tickets.attachments.disk'))
                ->temporaryUrl($filename, now()->addMinutes(5)),
        ];
    }
}
