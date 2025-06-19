<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\TicketPlugin;

class NotificationRecipientService
{
    public function getNotificationRecipients(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent $event
    ): Collection {
        $config = $this->getNotificationConfiguration();

        $eventName = $this->getEventName($event);

        $actorType = $this->getActorType($event);

        $settings = $config->getSettingsFor($eventName);
        $configuration = $settings->getSettingsFor($actorType);

        return $this->resolveRecipientsFromConfiguration($event, $configuration);
    }

    public function getUserNotificationStrategy(Authorizable $user): NotificationStrategy
    {
        if (method_exists($user, 'ticketNotificationStrategy')) {
            return $user->ticketNotificationStrategy();
        }
        /**
         * Error was here.  The config value was returning as a string.
         */
        $strat = config('padmission-tickets.default-notification-strategy', NotificationStrategy::Debounced);
        if (is_string($strat)) {
            try {
                $strat = NotificationStrategy::tryFrom($strat);
            } catch (\Throwable $e) {
                $strat = NotificationStrategy::Debounced;
            }
        }

        return $strat;
    }

    /**
     * Get notification configuration from the current plugin
     */
    protected function getNotificationConfiguration(): NotificationConfiguration
    {
        $plugin = TicketPlugin::get();

        return $plugin->getNotificationConfiguration();
    }

    /**
     * Determine the actor type based on the event's actor and ticket context
     */
    protected function getActorType($event): string
    {
        if (! $event->actor) {
            return 'user_triggered';
        }

        if ($event->actor->getKey() === $event->ticket->submitter_id) {
            return 'user_triggered';
        }

        if (\Gate::forUser($event->actor)->allows('update', $event->ticket)) {
            return 'supporter_triggered';
        }

        return 'user_triggered';
    }

    /**
     * Convert event to configuration event name
     */
    protected function getEventName($event): string
    {
        return match (get_class($event)) {
            TicketCreatedEvent::class => 'ticket_created',
            TicketActivityEvent::class => 'ticket_activity',
            TicketAssignedEvent::class => 'ticket_assigned',
            TicketClosedEvent::class => 'ticket_closed',
            default => 'ticket_activity',
        };
    }

    /**
     * Resolve actual recipients based on configuration
     */
    protected function resolveRecipientsFromConfiguration($event, array $configuration): Collection
    {
        $recipients = collect();
        $ticket = $event->ticket;

        $hasNotifyUser = $configuration['notify_user'] ?? false;
        $hasNotifySupporter = $configuration['notify_supporter'] ?? false;

        $hasUserChannels = collect($configuration)
            ->filter(fn ($value, $key) => ! str_starts_with($key, 'notify_') &&
                $value &&
                (str_ends_with($key, '_user') || str_ends_with($key, '_both'))
            )
            ->isNotEmpty();

        $hasSupporterChannels = collect($configuration)
            ->filter(fn ($value, $key) => ! str_starts_with($key, 'notify_') &&
                $value &&
                (str_ends_with($key, '_supporter') || str_ends_with($key, '_both'))
            )
            ->isNotEmpty();

        if (($hasNotifyUser || $hasUserChannels) && $ticket->submitter) {
            $recipients->push($ticket->submitter);
        }

        if (($hasNotifySupporter || $hasSupporterChannels) && $ticket->assignee) {
            $recipients->push($ticket->assignee);
        }

        return $recipients->unique(function ($user) {
            return $user->getKey();
        });
    }
}
