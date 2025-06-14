<?php

namespace Padmission\Tickets\Listeners;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Str;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\TicketPlugin;

class TicketNotificationListener
{
    public function __construct(
        protected NotificationRecipientService $recipientService
    ) {}

    /**
     * Handle the ticket event and dispatch notifications
     */
    public function handle(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent $event
    ): void {
        $recipients = $this->recipientService->getNotificationRecipients($event);
        $notificationType = $this->getNotificationType($event);

        if (! $notificationType) {
            return;
        }

        $recipients->each(function (Authorizable $user) use ($event, $notificationType) {
            $this->sendNotificationToUser($user, $event, $notificationType);
        });
    }

    /**
     * Get notification type from event class name
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
     * Send notification to a specific user
     */
    protected function sendNotificationToUser(Authorizable $user, $event, string $type): void
    {
        $strategy = $this->recipientService->getUserNotificationStrategy($user);

        if ($strategy === 'immediate') {
            $this->dispatchNotificationJob($user, $event->ticket, $type);
        } else {
            $this->dispatchDebouncedNotification($user, $event->ticket, $type);
        }
    }

    /**
     * Dispatch a notification job
     */
    protected function dispatchNotificationJob(Authorizable $user, Ticket $ticket, string $type): void
    {
        $jobClass = TicketPlugin::resolveJobClass(NotificationJob::class);
        $jobClass::dispatch($user, $ticket, $type);
    }

    /**
     * Dispatch a debounced notification
     *
     * This method uses the Laravel Queue Debouncer to ensure that:
     * 1. If no notification job exists for this user-ticket combination, schedule one for 5 minutes
     * 2. If a job already exists, cancel it and schedule a new one (resets the timer)
     * 3. This ensures active conversations don't trigger emails until 5 minutes of silence
     */
    protected function dispatchDebouncedNotification(Authorizable $user, Ticket $ticket, string $type): void
    {
        $debounceTime = config('padmission-tickets.notification-debounce', 300);
        $jobClass = TicketPlugin::resolveJobClass(NotificationJob::class);
        $job = new $jobClass($user, $ticket, $type);

        // Use dependency injection to get the debouncer and customize the cache key provider
        $debouncer = app(\Mpbarlow\LaravelQueueDebouncer\Debouncer::class);

        $debouncer
            ->usingCacheKeyProvider(fn () => $job->uniqueId())
            ->debounce($job, $debounceTime);
    }
}
