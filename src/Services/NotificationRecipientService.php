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
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Events\TicketPriorityChangedEvent;
use Padmission\Tickets\Events\TicketStatusChangedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class NotificationRecipientService
{
    public function getNotificationRecipients(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent|TicketHandedOverEvent|TicketPriorityChangedEvent|TicketStatusChangedEvent $event
    ): Collection {
        if ($event instanceof TicketHandedOverEvent) {
            return $this->getHandOverRecipients($event);
        }

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
        if (($recipientFlag & NotificationRecipient::Supporter->value) === NotificationRecipient::Supporter->value) {
            $assignee = $this->getAssignee($event->ticket);

            if ($assignee) {
                $recipients->push($assignee);
            } else {
                $recipients = $recipients->merge($this->getFallbackSupporters($event->ticket, $event->actor));
            }
        }

        return $recipients->filter()->unique(fn ($user) => $user->getKey());
    }

    /*
     * Only the two people the escalation moved between hear about it; the
     * other team learns of it from the history note like any other activity.
     */
    private function getHandOverRecipients(TicketHandedOverEvent $event): Collection
    {
        $ids = collect([$event->toId, $event->fromId])
            ->filter()
            ->reject(fn (int|string $id): bool => $event->actor !== null && (string) $id === (string) $event->actor->getAuthIdentifier())
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return TicketPlugin::resolveUserModelClass()::query()
            ->withoutGlobalScopes()
            ->whereKey($ids->all())
            ->get()
            ->values();
    }

    private function getAssignee(Ticket $ticket): ?Authenticatable
    {
        $assignee = TicketAssignee::for($ticket);

        return $assignee instanceof Authenticatable ? $assignee : null;
    }

    private function getFallbackSupporters(Ticket $ticket, ?Authenticatable $actor): Collection
    {
        $supportersQuery = TicketPlugin::get($ticket->panel)->getAllSupportersQuery();

        if (! $supportersQuery) {
            return collect();
        }

        return app()->call($supportersQuery, ['ticket' => $ticket])
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->getKey()))
            ->when($ticket->submitter_id, fn ($query) => $query->whereKeyNot($ticket->submitter_id))
            ->get();
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
