<?php

namespace Padmission\Tickets\Services;

use Illuminate\Support\Collection;
use Padmission\Tickets\Models\Contracts\TicketInterface;
use Padmission\Tickets\Models\TicketNotification;

class TicketActivityService
{
    /**
     * Get unread activities for a ticket within the specified time range
     */
    public function getUnreadActivities(
        TicketInterface $ticket,
        $notifiable,
        int $maxEvents,
        int $maxDays
    ): Collection {
        $lastNotification = $this->getLastNotification($ticket, $notifiable);

        return $ticket
            ->ticketActivities()
            ->with('user')
            ->where('created_at', '>', now()->subDays($maxDays))
            ->where('created_at', '<=', now())
            ->when($lastNotification, function ($query) use ($lastNotification) {
                $query->where('created_at', '>', $lastNotification->updated_at);
            })
            ->orderBy('created_at', 'asc')
            ->limit($maxEvents)
            ->get();
    }

    /**
     * Get the last notification record for a user and ticket
     */
    public function getLastNotification(TicketInterface $ticket, $notifiable): ?TicketNotification
    {
        /** @var \Padmission\Tickets\Models\TicketNotification|null $notification */
        $notification = $ticket
            ->ticketNotifications()
            ->where('user_id', $notifiable->getKey())
            ->latest()
            ->first();

        return $notification;
    }

    /**
     * Mark notification as updated or create a new notification record
     */
    public function markNotificationUpdated(TicketInterface $ticket, $notifiable): void
    {
        $lastNotification = $this->getLastNotification($ticket, $notifiable);

        if ($lastNotification) {
            $lastNotification->update([
                'updated_at' => now(),
            ]);
        } else {
            $ticket->ticketNotifications()->create([
                'user_id' => $notifiable->getKey(),
            ]);
        }
    }
}
