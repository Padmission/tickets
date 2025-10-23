<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Models\Ticket;

class TicketAuth
{
    public function getUserId(): int|string|null
    {
        return Filament::auth()->id() ?? session()->get('padmission-tickets::user_key');
    }

    public function authorizeTicketAccess(Ticket $ticket, ?Authenticatable $user): void
    {
        $currentSender = $user?->getAuthIdentifier() === $ticket->submitter_id
            ? ActivitySender::User
            : ActivitySender::Supporter;

        $isAuthorized = $currentSender === ActivitySender::User
            || $user?->can('manage', $ticket);

        abort_unless($isAuthorized, 403);
    }
}
