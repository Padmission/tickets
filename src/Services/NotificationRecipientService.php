<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;

class NotificationRecipientService
{
    /**
     * Get the list of users who should receive notifications for this event
     */
    public function getNotificationRecipients(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent $event
    ): Collection {
        return collect([$event->ticket?->assignee, $event->ticket?->submitter])
            ->filter()
            ->unique(function ($user) {
                return $user->getKey();
            });
    }

    /**
     * Get the notification strategy for a user
     */
    public function getUserNotificationStrategy(Authorizable $user): string
    {
        if (method_exists($user, 'ticketNotificationStrategy')) {
            return $user->ticketNotificationStrategy();
        }

        return config('padmission-tickets.default-notification-strategy', 'debounced');
    }
}
