<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Enums\NotificationTrigger;
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
        $eventName = $event::class;
        $triggerType = $this->determineTriggerType($event);

        $recipientFlag = TicketPlugin::get()
            ->getNotificationConfiguration()
            ->getConfigurationFor($eventName, $triggerType)
            ->value;

        $recipients = collect();

        if (
            $event->ticket->submitter
            && ($recipientFlag & NotificationRecipient::User->value) === NotificationRecipient::User->value
        ) {
            $recipients->push($event->ticket->submitter);
        }
        if (
            $event->ticket->assignee
            && ($recipientFlag & NotificationRecipient::Supporter->value) === NotificationRecipient::Supporter->value
        ) {
            $recipients->push($event->ticket->assignee);
        }

        return $recipients->filter()->unique('id');
    }

    private function determineTriggerType($event): NotificationTrigger
    {
        if (! $event->actor || $event->actor->getKey() === $event->ticket->submitter_id) {
            return NotificationTrigger::User;
        }

        return Gate::forUser($event->actor)->allows('update', $event->ticket)
            ? NotificationTrigger::Supporter
            : NotificationTrigger::User;
    }

    public function getUserNotificationStrategy(Authenticatable $user): NotificationStrategy
    {
        if (method_exists($user, 'ticketNotificationStrategy')) {
            return $user->ticketNotificationStrategy();
        }

        return config('padmission-tickets.default-notification-strategy', NotificationStrategy::Debounced);
    }
}
