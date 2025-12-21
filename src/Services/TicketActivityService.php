<?php

namespace Padmission\Tickets\Services;

use Illuminate\Support\Collection;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketLastSeen;

class TicketActivityService
{
    /**
     * Get unread activities for a ticket (for email notifications)
     */
    public function getUnreadActivities(
        Ticket $ticket,
        $notifiable,
        int $maxEvents,
        int $maxDays
    ): Collection {
        $lastSeen = $this->getLastSeen($ticket, $notifiable);

        return $ticket
            ->ticketActivities()
            ->with('user')
            ->when($lastSeen?->last_notified_activity_id, function ($query) use ($lastSeen) {
                $query->where('id', '>', $lastSeen->last_notified_activity_id);
            })
            ->where('created_at', '>=', now()->subDays($maxDays))
            ->orderBy('created_at', 'asc')
            ->limit($maxEvents)
            ->get();
    }

    /**
     * Get the last seen record for a user and ticket
     */
    public function getLastSeen(Ticket $ticket, $notifiable): ?TicketLastSeen
    {
        /** @var TicketLastSeen|null $lastSeen */
        $lastSeen = $ticket
            ->ticketLastSeen()
            ->where('user_id', $notifiable->getKey())
            ->first();

        return $lastSeen;
    }

    /**
     * Mark specific activity as seen by user
     */
    public function markActivitySeen(Ticket $ticket, $notifiable, int $activityId): void
    {
        $ticket->ticketLastSeen()->updateOrCreate(
            [
                'user_id' => $notifiable->getKey(),
                'ticket_id' => $ticket->id,
            ],
            [
                'last_seen_activity_id' => $activityId,
            ]
        );
    }

    /**
     * Mark notification email as sent (separate from viewing)
     */
    public function markNotificationSent(Ticket $ticket, $notifiable, int $activityId): void
    {
        $ticket->ticketLastSeen()->updateOrCreate(
            [
                'user_id' => $notifiable->getKey(),
                'ticket_id' => $ticket->id,
            ],
            [
                'last_notified_activity_id' => $activityId,
            ]
        );
    }

    /**
     * @deprecated Use markNotificationSent() instead
     */
    public function markNotificationUpdated(Ticket $ticket, $notifiable): void
    {
        // For backward compatibility - get the latest activity and mark it as notified
        $latestActivity = $ticket->ticketActivities()->latest('id')->first();

        if ($latestActivity) {
            $this->markNotificationSent($ticket, $notifiable, $latestActivity->id);
        }
    }
}
