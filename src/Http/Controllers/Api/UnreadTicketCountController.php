<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class UnreadTicketCountController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request)
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $this->authorize('create', $ticketModel);

        $userId = $request->user()->id;

        // Count tickets where:
        // 1. User is the submitter
        // 2. Either no last_seen record exists, OR
        // 3. The ticket has activities with IDs greater than last_seen_activity_id
        $unreadCount = $ticketModel::query()
            ->where('submitter_id', $userId)
            ->where(function ($query) use ($userId) {
                $query
                    ->whereDoesntHave('ticketLastSeen', fn ($query) => $query->where('user_id', $userId))
                    ->orWhereHas('ticketActivities', function ($activityQuery) use ($userId) {
                        $activityQuery->whereRaw(
                            'ticket_activities.id > COALESCE(
                                (
                                    SELECT last_seen_activity_id
                                        FROM ticket_last_seen
                                        WHERE ticket_last_seen.ticket_id = tickets.id
                                            AND ticket_last_seen.user_id = ?
                                            AND ticket_last_seen.last_seen_activity_id IS NOT NULL
                                        LIMIT 1
                                ),
                                0
                            )',
                            [$userId]
                        );
                    });
            })
            ->count();

        return [
            'unread_count' => $unreadCount,
        ];
    }
}
