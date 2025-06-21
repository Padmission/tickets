<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;

class NotificationRecipientService
{
    public function getNotificationRecipients(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent $event
    ): Collection {
        return collect([$event->ticket?->assignee, $event->ticket?->submitter])
            ->filter()
            ->unique(function ($user) {
                return $user->getKey();
            });
    }

    public function getUserNotificationStrategy(Authenticatable $user): NotificationStrategy
    {
        if (method_exists($user, 'ticketNotificationStrategy')) {
            return $user->ticketNotificationStrategy();
        }

        return config('padmission-tickets.default-notification-strategy', NotificationStrategy::Debounced);
    }
}
