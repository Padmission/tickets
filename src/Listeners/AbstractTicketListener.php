<?php

namespace Padmission\Tickets\Listeners;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mpbarlow\LaravelQueueDebouncer\Facade\Debouncer;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;

abstract class AbstractTicketListener
{
    /**
     * Handle the ticket event and dispatch notifications.
     */
    public function handle(TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent $event): void
    {
        $recipients = $this->getNotificationRecipients($event);

        $recipients->each(function (Authorizable $user) use ($event) {
            $this->sendNotificationToUser($user, $event);
        });
    }

    /**
     * Get the list of users who should receive notifications for this event.
     *
     * @param  TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent  $event
     * @return \Illuminate\Support\Collection<Authorizable>
     */
    protected function getNotificationRecipients($event): \Illuminate\Support\Collection
    {
        return collect([$event->ticket?->assignee, $event->ticket?->submitter])
            ->filter()
            ->unique(function ($user) {
                return $user->getKey();
            });
    }

    /**
     * Send notification to a specific user.
     *
     * @param  TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent  $event
     */
    protected function sendNotificationToUser(Authorizable $user, $event): void
    {
        $type = $this->getNotificationType($event);

        if (! $type) {
            return;
        }

        $strategy = $this->getUserNotificationStrategy($user);

        if ($strategy === 'immediate') {
            NotificationJob::dispatch($user, $event->ticket, $type);
        } else {
            $this->dispatchDebouncedNotification($user, $event->ticket, $type);
        }
    }

    /**
     * Get notification type from event class name.
     *
     * @param  TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent  $event
     */
    protected function getNotificationType($event): ?string
    {
        $type = strtolower(
            Str::chopEnd(               // remove trailing 'Event'
                Str::chopStart(          // remove leading 'Ticket'
                    class_basename($event::class),
                    'Ticket'
                ),
                'Event'
            )
        );
        return $type ?: null;
    }

    /**
     * Dispatch a debounced notification job.
     *
     * @param  \Padmission\Tickets\Models\Ticket  $ticket
     */
    protected function dispatchDebouncedNotification(Authorizable $user, $ticket, string $type): void
    {
        $debounceTime = config('padmission-tickets.notification-debounce', 300);
        $job = new NotificationJob($user, $ticket, $type);

        $jobId = $job->uniqueId();

        if (Cache::has($jobId)) {
            return;
        }

        Cache::put($jobId, true, now()->addSeconds($debounceTime));

        Debouncer::usingCacheKeyProvider(fn () => $job->uniqueId())
            ->debounce($job, $debounceTime);
    }

    /**
     * Get the notification strategy for a user.
     */
    protected function getUserNotificationStrategy(Authorizable $user): string
    {
        if (method_exists($user, 'ticketNotificationStrategy')) {
            return $user->ticketNotificationStrategy();
        }

        return config('padmission-tickets.default-notification-strategy', 'debounced');
    }
}
