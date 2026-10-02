<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

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

    /*
     * Asked of the panel the chat was opened in, then of the ticket's. The
     * chat names its panel in a header, since its API runs outside any panel,
     * so a header is only a hint: one naming a panel the user may not enter
     * is ignored, and the ticket's own panel is always asked as well, or a
     * caller could name a panel without the rule to write past it.
     */
    public function replyDisabledReason(Ticket $ticket, ?Authenticatable $user): ?string
    {
        if ($user === null) {
            return null;
        }

        foreach (array_unique(array_filter([$this->chatPanelId($user), $ticket->panel])) as $panelId) {
            $reason = TicketPlugin::find($panelId)?->getReplyDisabledReason($ticket, $user);

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    /*
     * A new ticket has no row yet, so the rule is asked of one as it would be
     * written: in the panel it goes to, from the person writing it.
     */
    public function refuseDisabledNewTicket(string $panelId, Authenticatable $user): void
    {
        $draft = new (TicketPlugin::resolveModelClass(Ticket::class));
        $draft->forceFill([
            'panel' => $panelId,
            'source_panel' => Filament::getCurrentOrDefaultPanel()?->getId(),
            'submitter_id' => $user->getAuthIdentifier(),
        ]);

        $this->refuseDisabledReply($draft, $user);
    }

    protected function chatPanelId(Authenticatable $user): ?string
    {
        $named = Str::after((string) request()->header('X-Padmission-Tickets-Panel'), 'panel-');

        if ($named === '') {
            return Filament::getCurrentPanel()?->getId();
        }

        $panel = Filament::getPanels()[$named] ?? null;

        if ($panel === null || ($user instanceof FilamentUser && ! $user->canAccessPanel($panel))) {
            return null;
        }

        return $named;
    }

    public function refuseDisabledReply(Ticket $ticket, ?Authenticatable $user): void
    {
        $reason = $this->replyDisabledReason($ticket, $user);

        if ($reason !== null) {
            throw new HttpResponseException(response()->json(['message' => $reason], 403));
        }
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
