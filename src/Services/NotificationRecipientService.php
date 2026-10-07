<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Enums\NotificationTrigger;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Events\TicketPriorityChangedEvent;
use Padmission\Tickets\Events\TicketReopenedEvent;
use Padmission\Tickets\Events\TicketStatusChangedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class NotificationRecipientService
{
    public function getNotificationRecipients(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent|TicketHandedOverEvent|TicketReopenedEvent|TicketPriorityChangedEvent|TicketStatusChangedEvent $event
    ): Collection {
        if ($event instanceof TicketHandedOverEvent) {
            return $this->getHandOverRecipients($event);
        }

        $eventName = $event::class;
        $triggerType = $this->determineTriggerType($event);

        // Queue workers have no current panel; notification rules belong to
        // the ticket's own panel, like its assignee and relationship scopes.
        $configuration = TicketPlugin::find($event->ticket->panel)?->getNotificationConfiguration()
            ?? NotificationConfiguration::make();

        $recipientFlag = $configuration->getConfigurationFor($eventName, $triggerType)->value;

        // A delayed close event stays silent after reopening a duplicate. A later
        // ordinary close can still use the host's explicit requester opt in.
        $duplicateClose = $event instanceof TicketClosedEvent
            && $event->ticket->ticketActivities()
                ->whereIn('type', [ActivityType::Closed, ActivityType::ClosedAsDuplicate])
                ->latest('id')->value('type') === ActivityType::ClosedAsDuplicate;

        $isRequesterSilent = $event instanceof TicketClosedEvent || $event instanceof TicketReopenedEvent || $event instanceof TicketAssignedEvent
            || ($event instanceof TicketActivityEvent && in_array($event->activityType, [ActivityType::Closed->value, ActivityType::Reopened->value, ActivityType::AssigneeChanged->value, ActivityType::ClosedAsDuplicate->value, ActivityType::DuplicatedBy->value, ActivityType::DuplicateRemoved->value], true));

        // Close/reopen and assignment history notes stay in chat without notifying the requester.
        if ($duplicateClose || $event instanceof TicketReopenedEvent || $event instanceof TicketAssignedEvent
            || ($event instanceof TicketActivityEvent && in_array($event->activityType, [ActivityType::Closed->value, ActivityType::Reopened->value, ActivityType::AssigneeChanged->value, ActivityType::ClosedAsDuplicate->value, ActivityType::DuplicatedBy->value, ActivityType::DuplicateRemoved->value], true))) {
            $recipientFlag &= ~NotificationRecipient::User->value;
        }

        $recipients = collect();

        if (($recipientFlag & NotificationRecipient::User->value) === NotificationRecipient::User->value) {
            $recipients->push($this->getSubmitter($event->ticket));
        }
        if (($recipientFlag & NotificationRecipient::Supporter->value) === NotificationRecipient::Supporter->value) {
            $assignee = $this->getAssignee($event->ticket);

            if ($assignee) {
                // An escalation's requester may also be a supporter or assignee.
                if (! $isRequesterSilent
                    || (string) $assignee->getKey() !== (string) $event->ticket->submitter_id) {
                    $recipients->push($assignee);
                }
            } else {
                $fallback = $this->getFallbackSupporters($event->ticket, $event->actor);

                if ($event instanceof TicketAssignedEvent
                    || ($event instanceof TicketActivityEvent && $event->activityType === ActivityType::AssigneeChanged->value)) {
                    $fallback = $fallback->reject(fn ($user): bool => $event->ticket->isSubmittedBy($user));
                }

                $recipients = $recipients->merge($fallback);
            }
        }

        // Creation can tell just the assignee without enabling the supporter
        // pool fallback. None still disables all creation notifications.
        if ($event instanceof TicketCreatedEvent) {
            if ($recipientFlag !== NotificationRecipient::None->value && $configuration->shouldNotifyAssigneeOnCreation()) {
                $recipients->push($this->getAssignee($event->ticket));
            }

            // Keep the requester's acknowledgement, but never queue a notice
            // to someone who assigned the ticket to themselves.
            $recipients = $recipients->reject(fn ($user): bool => $user !== null
                && $event->actor !== null
                && (string) $user->getKey() === (string) $event->actor->getAuthIdentifier()
                && (string) $user->getKey() !== (string) $event->ticket->submitter_id);
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

    /*
     * Found through the ticket's own panel, as the assignee is: a reply can
     * come through the chat's API, where no panel lifts the sender's tenant
     * scope from someone who escalated from another tenant.
     */
    private function getSubmitter(Ticket $ticket): ?Authenticatable
    {
        if (blank($ticket->submitter_id)) {
            return null;
        }

        $relation = $ticket->submitter();
        $modifier = $ticket->isNotInCurrentPanel()
            ? TicketPlugin::find($ticket->panel)?->getRelationshipScopeModifier()
            : null;

        if ($modifier !== null) {
            app()->call($modifier, ['relation' => $relation, 'model' => 'submitter']);
        }

        $submitter = $relation->first();

        return $submitter instanceof Authenticatable ? $submitter : null;
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
