<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\TicketPlugin;

class TemporaryAttachmentUrlController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, int $ticket): array
    {
        $request->validate([
            'filepath' => ['required', 'string', 'max:255'],
        ]);

        // Remove global scopes to find the ticket and get its panel
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $tempTicket = $ticketModel::withoutGlobalScopes()->findOrFail($ticket);

        // Get the plugin for this ticket's panel and verify against custom query
        $panelPlugin = TicketPlugin::get($tempTicket->panel);
        $ticketRecord = $panelPlugin->getTicketQuery()->findOrFail($ticket);

        app(TicketAuth::class)->authorizeTicketAccess($ticketRecord, $request->user());

        $filepath = $request->input('filepath');

        // Verify the filepath belongs to an attachment on this ticket
        $attachment = $ticketRecord->attachments()
            ->where('filepath', $filepath)
            ->firstOrFail();

        return [
            'url' => Storage::disk(config('padmission-tickets.attachments.disk'))
                ->temporaryUrl($attachment->filepath, now()->addMinutes(5)),
        ];
    }
}
