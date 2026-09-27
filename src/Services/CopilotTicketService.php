<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\TicketPlugin;

class CopilotTicketService
{
    /**
     * @return EloquentCollection<int, Ticket>
     */
    public function visibleTickets(Authenticatable&Model $user, string $filter = 'open'): EloquentCollection
    {
        $query = TicketPlugin::get()
            ->getTicketQuery()
            ->with(['assignee', 'disposition', 'latestMessage', 'priority', 'status'])
            ->withExists([
                'ticketActivities as has_unread_support_response' => fn (Builder $query) => $this->withUnreadSupportResponseActivityConstraint($query, $user),
            ])
            ->where('submitter_id', $user->getAuthIdentifier())
            ->withoutEscalations()
            ->latest('updated_at')
            ->limit(50);

        match ($filter) {
            'closed' => $query->closed(),
            'all' => null,
            default => $query->open(),
        };

        /** @var EloquentCollection<int, Ticket> $tickets */
        $tickets = $query->get()
            ->filter(fn (Ticket $ticket): bool => Gate::forUser($user)->allows('view', $ticket))
            ->values();

        return $tickets;
    }

    public function unreadResponseTicketCount(Authenticatable&Model $user): int
    {
        $query = TicketPlugin::get()
            ->getTicketQuery()
            ->where('submitter_id', $user->getAuthIdentifier())
            ->withoutEscalations()
            ->open();

        $this->withUnreadSupportResponseConstraint($query, $user);

        /** @var EloquentCollection<int, Ticket> $tickets */
        $tickets = $query->get();

        return $tickets
            ->filter(fn (Ticket $ticket): bool => Gate::forUser($user)->allows('view', $ticket))
            ->count();
    }

    public function findVisibleTicket(Authenticatable&Model $user, int $ticketId): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = TicketPlugin::get()
            ->getTicketQuery()
            ->with(['assignee', 'disposition', 'priority', 'status'])
            ->where('submitter_id', $user->getAuthIdentifier())
            ->withoutEscalations()
            ->findOrFail($ticketId);

        Gate::forUser($user)->authorize('view', $ticket);

        return $ticket;
    }

    public function markTicketSeen(Authenticatable&Model $user, Ticket $ticket): void
    {
        Gate::forUser($user)->authorize('view', $ticket);

        $latestSupportResponseId = $ticket
            ->ticketActivities()
            ->where('type', ActivityType::Message->value)
            ->where('sender', ActivitySender::Supporter->value)
            ->latest('id')
            ->value('id');

        if (! $latestSupportResponseId) {
            return;
        }

        app(TicketActivityService::class)->markAsSeen($ticket, $user, $latestSupportResponseId);
    }

    public function resolveTicket(Authenticatable&Model $user, Ticket $ticket): void
    {
        Gate::forUser($user)->authorize('view', $ticket);

        $isSubmitter = (int) $ticket->submitter_id === (int) $user->getAuthIdentifier();
        $canManage = Gate::forUser($user)->allows('manage', $ticket);

        if (! $isSubmitter && ! $canManage) {
            Gate::forUser($user)->authorize('manage', $ticket);
        }

        $ticket->close(closedById: (int) $user->getAuthIdentifier());
    }

    public function withUnreadSupportResponseConstraint(Builder $query, Authenticatable&Model $user): void
    {
        $ticketTable = $query->getModel()->getTable();
        $activityTable = (new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable();
        $userStateTable = (new (TicketPlugin::resolveModelClass(TicketUserState::class)))->getTable();

        $query->whereExists(function ($query) use ($activityTable, $ticketTable, $user, $userStateTable): void {
            $query
                ->selectRaw('1')
                ->from($activityTable)
                ->leftJoin($userStateTable, function ($join) use ($ticketTable, $user, $userStateTable): void {
                    $join
                        ->on("{$userStateTable}.ticket_id", '=', "{$ticketTable}.id")
                        ->where("{$userStateTable}.user_id", '=', $user->getAuthIdentifier());
                })
                ->whereColumn("{$activityTable}.ticket_id", "{$ticketTable}.id")
                ->where("{$activityTable}.type", ActivityType::Message->value)
                ->where("{$activityTable}.sender", ActivitySender::Supporter->value)
                ->where(function ($query) use ($activityTable, $userStateTable): void {
                    $query
                        ->whereNull("{$userStateTable}.last_seen_activity_id")
                        ->orWhereColumn("{$activityTable}.id", '>', "{$userStateTable}.last_seen_activity_id");
                });
        });
    }

    protected function withUnreadSupportResponseActivityConstraint(Builder $query, Authenticatable&Model $user): void
    {
        $activityTable = $query->getModel()->getTable();
        $userStateTable = (new (TicketPlugin::resolveModelClass(TicketUserState::class)))->getTable();

        $query
            ->leftJoin($userStateTable, function ($join) use ($activityTable, $user, $userStateTable): void {
                $join
                    ->on("{$userStateTable}.ticket_id", '=', "{$activityTable}.ticket_id")
                    ->where("{$userStateTable}.user_id", '=', $user->getAuthIdentifier());
            })
            ->where("{$activityTable}.type", ActivityType::Message->value) // @phpstan-ignore argument.type
            ->where("{$activityTable}.sender", ActivitySender::Supporter->value) // @phpstan-ignore argument.type
            ->where(function ($query) use ($activityTable, $userStateTable): void {
                $query
                    ->whereNull("{$userStateTable}.last_seen_activity_id")
                    ->orWhereColumn("{$activityTable}.id", '>', "{$userStateTable}.last_seen_activity_id");
            });
    }
}
