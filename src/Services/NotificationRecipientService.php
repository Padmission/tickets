<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Events\TicketPriorityChangedEvent;
use Padmission\Tickets\Events\TicketStatusChangedEvent;
use Padmission\Tickets\TicketPlugin;

class NotificationRecipientService
{
    public function getNotificationRecipients(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent|TicketPriorityChangedEvent|TicketStatusChangedEvent $event
    ): Collection {
        $eventName = $this->getEventName($event);
        $triggerType = $this->determineTriggerType($event);

        $config = TicketPlugin::get()
            ->getNotificationConfiguration()
            ->getConfigurationFor($eventName, $triggerType);

        $recipients = collect();

        if (($config['notify_user'] ?? false) && $event->ticket->submitter) {
            $recipients->push($event->ticket->submitter);
        }
        if (($config['notify_supporter'] ?? false) && $event->ticket->assignee) {
            $recipients->push($event->ticket->assignee);
        }

        return $recipients->filter()->unique('id');
    }

    private function determineTriggerType($event): string
    {
        if (! $event->actor || $event->actor->getKey() === $event->ticket->submitter_id) {
            return 'user_triggered';
        }

        return Gate::forUser($event->actor)->allows('update', $event->ticket)
            ? 'supporter_triggered'
            : 'user_triggered';
    }

    private function getEventName($event): string
    {
        $class = get_class($event);
        $map = [
            TicketCreatedEvent::class => 'ticket_created',
            TicketAssignedEvent::class => 'ticket_assigned',
            TicketActivityEvent::class => 'ticket_activity',
            TicketClosedEvent::class => 'ticket_closed',
            TicketPriorityChangedEvent::class => 'ticket_activity',
            TicketStatusChangedEvent::class => 'ticket_activity',
        ];

        return $map[$class] ?? 'ticket_activity';
    }

    public function getUserNotificationStrategy(Authenticatable $user): NotificationStrategy
    {
        if (method_exists($user, 'ticketNotificationStrategy')) {
            return $user->ticketNotificationStrategy();
        }

        return config('padmission-tickets.default-notification-strategy', NotificationStrategy::Debounced);
    }
}
