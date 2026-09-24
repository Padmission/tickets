<?php

namespace Padmission\Tickets\Services;

use Filament\Facades\Filament;
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
use Padmission\Tickets\Models\Ticket;
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
     * The assignee relation carries the acting panel's scopes. A ticket linked
     * into another panel can be assigned to someone only that panel's scopes
     * reveal (a cross-tenant support panel, say); seen from the acting panel
     * the assignee vanishes and every fallback supporter is notified instead.
     */
    private function getAssignee(Ticket $ticket): ?Authenticatable
    {
        if (! $ticket->assignee_id) {
            return null;
        }

        $relation = $ticket->assignee();
        $modifier = $ticket->isNotInCurrentPanel()
            ? $this->getPluginForPanel($ticket->panel)?->getRelationshipScopeModifier()
            : null;

        if ($modifier) {
            $relation = app()->call($modifier, ['relation' => $relation, 'model' => 'assignee']);
        }

        return $relation->first();
    }

    /*
     * A host may leave a panel's plugin unregistered in some processes, such as
     * queue workers, and ticket events can fire there too.
     */
    private function getPluginForPanel(?string $panelId): ?TicketPlugin
    {
        $panel = Filament::getPanels()[$panelId] ?? null;

        if (! $panel?->hasPlugin(TicketPlugin::$id)) {
            return null;
        }

        /** @var TicketPlugin */
        return $panel->getPlugin(TicketPlugin::$id);
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
