<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Services\ApiTicketResolver;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Support\AttachmentTypes;

class TemporaryAttachmentUrlController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, int $ticket): array
    {
        $request->validate([
            'filepath' => ['required', 'string', 'max:255'],
        ]);

        $ticketRecord = resolve(ApiTicketResolver::class)->resolve($ticket, $request->user());

        app(TicketAuth::class)->authorizeTicketAccess($ticketRecord, $request->user());

        $filepath = $request->input('filepath');

        // Verify the filepath belongs to an attachment on this ticket
        $attachment = $ticketRecord->attachments()
            ->where('filepath', $filepath)
            ->firstOrFail();

        abort_unless($this->canSee($ticketRecord, $attachment, $request->user()), 404);

        return [
            'url' => Storage::disk(config('padmission-tickets.attachments.disk'))
                ->temporaryUrl($attachment->filepath, now()->addMinutes(5), AttachmentTypes::downloadOptions($attachment->mime_type)),
        ];
    }

    /*
     * A file is seen with the message it was sent on, so it is signed only for
     * someone who sees that message: a requester never gets an internal note's.
     * One not sent yet is its uploader's alone.
     */
    protected function canSee(Ticket $ticket, TicketAttachment $attachment, ?Authenticatable $user): bool
    {
        if ($attachment->activity_id === null) {
            return $user !== null && (string) $attachment->created_by === (string) $user->getAuthIdentifier();
        }

        $sender = $ticket->isSubmittedBy($user) ? ActivitySender::User : ActivitySender::Supporter;

        return $ticket->ticketActivities()
            ->whereKey($attachment->activity_id)
            ->whereIn('type', resolve(TicketActivityService::class)->getActivityTypesForSender($ticket, $sender, $user))
            ->exists();
    }
}
