<?php

namespace Padmission\Tickets\Http\DataMappers\StaffApi;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Services\TicketCloser;
use Padmission\Tickets\Services\TicketReopening;
use Padmission\Tickets\Support\ConversationState;
use Padmission\Tickets\TicketPlugin;

/*
 * Tickets as the staff API returns them: plain values a client can render
 * without the panel's HTML, with the viewer's own unread and waiting state.
 */
class StaffTicketMapper
{
    /**
     * Without a state, the ticket must be a row from a query that loaded it (withConversationState).
     *
     * @return array<string, mixed>
     */
    public static function summary(Ticket $ticket, Authenticatable $viewer, ?ConversationState $state = null): array
    {
        $state ??= ConversationState::fromRow($ticket);
        $latest = $ticket->latestMessage;

        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'origin' => TicketPlugin::get()->describeTicketOrigin($ticket),
            'status' => static::lookup($ticket->status),
            'priority' => static::lookup($ticket->priority),
            'assignee' => static::person($ticket->assignee),
            'submitter' => static::submitter($ticket),
            'waiting_on' => $state->waitingOn,
            'needs_you' => ! $ticket->isClosed && $state->rank === 0,
            'is_new' => $state->isNew,
            'is_closed' => $ticket->isClosed,
            'is_unread' => $ticket->hasUnreadMessagesFor($viewer),
            'latest_message' => $latest ? [
                'text' => $latest->plainTextContent(40),
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ];
    }

    /**
     * One ticket with what the viewer may do to it, so a client shows only the actions that will work.
     *
     * @return array<string, mixed>
     */
    public static function detail(Ticket $ticket, ConversationState $state, Authenticatable $viewer): array
    {
        $auth = resolve(TicketAuth::class);
        $replyBlocked = $auth->replyDisabledReason($ticket, $viewer);
        $canReply = $auth->canReply($ticket, $viewer) && $replyBlocked === null;

        return [
            ...static::summary($ticket, $viewer, $state),
            'disposition' => static::lookup($ticket->disposition),
            'escalation' => [
                'is_escalation' => $state->isEscalation,
                'originals' => $ticket->childTickets->map(fn (Ticket $original): array => [
                    'id' => $original->id,
                    'subject' => $original->subject,
                    'origin' => TicketPlugin::get()->describeTicketOrigin($original),
                ])->values()->all(),
            ],
            'can' => [
                'reply' => $canReply && ! $ticket->isClosed,
                'reply_disabled_reason' => $replyBlocked,
                'update' => Gate::forUser($viewer)->allows('update', $ticket),
                'close' => resolve(TicketCloser::class)->canClose($ticket),
                'reopen' => in_array(TicketReopening::REOPEN, resolve(TicketReopening::class)->choicesFor($ticket, $viewer), true),
            ],
        ];
    }

    /**
     * @return array{id: int|string, name: string, color: ?string}|null
     */
    public static function lookup(?Model $lookup): ?array
    {
        if ($lookup === null) {
            return null;
        }

        return [
            'id' => $lookup->getKey(),
            'name' => (string) $lookup->getAttribute('display_name'),
            'color' => $lookup->getAttribute('color_palette')[500] ?? null,
        ];
    }

    /**
     * @return array{id: int|string, name: string, email: ?string}|null
     */
    public static function person(?Model $person): ?array
    {
        if ($person === null) {
            return null;
        }

        return [
            'id' => $person->getKey(),
            'name' => (string) ($person->getAttribute('name') ?? $person->getAttribute('email')),
            'email' => $person->getAttribute('email'),
        ];
    }

    /**
     * Someone without an account writes as a guest, named only on the ticket.
     *
     * @return array{id: int|string|null, name: ?string, email: ?string}|null
     */
    public static function submitter(Ticket $ticket): ?array
    {
        if ($ticket->submitter) {
            return static::person($ticket->submitter);
        }

        $guest = $ticket->submitter_data;

        return $guest ? ['id' => null, 'name' => $guest->name, 'email' => $guest->email] : null;
    }
}
