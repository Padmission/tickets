<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;

class TicketAuth
{
    public function getUserId(): int|string|null
    {
        return Filament::auth()->id() ?? session()->get('padmission-tickets::user_key');
    }

    public function authorizeTicketAccess(Ticket $ticket, ?Authenticatable $user): void
    {
        abort_unless($this->canAccess($ticket, $user), 403);
    }

    public function canAccess(Ticket $ticket, ?Authenticatable $user): bool
    {
        if ($user?->getAuthIdentifier() === $ticket->submitter_id) {
            return true;
        }

        return $user !== null
            && Gate::forUser($user)->allows('view', $ticket)
            && Gate::forUser($user)->allows('manage', $ticket);
    }

    public function authorizeReply(Ticket $ticket, ?Authenticatable $user): void
    {
        abort_unless($this->canReply($ticket, $user), 403);
    }

    public function refuseClosedTicket(Ticket $ticket): void
    {
        if ($ticket->isClosed) {
            throw new HttpResponseException(response()->json([
                'message' => __('padmission-tickets::tickets.copilot.closed_ticket_reply_error'),
            ], 422));
        }
    }

    /*
     * Reading and managing a ticket is not the same as writing to its
     * requester: a team that answers escalations may see an original but must
     * not post on it. A host policy without a reply ability keeps the old rule.
     */
    public function canReply(Ticket $ticket, ?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->getAuthIdentifier() === $ticket->submitter_id) {
            return true;
        }

        $gate = Gate::forUser($user);

        if (! $gate->allows('view', $ticket) || ! $gate->allows('manage', $ticket)) {
            return false;
        }

        $policy = Gate::getPolicyFor($ticket);

        return $policy === null || ! method_exists($policy, 'reply') || $gate->allows('reply', $ticket);
    }
}
