<?php

namespace Padmission\Tickets\Listeners;

use Carbon\CarbonInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Mpbarlow\LaravelQueueDebouncer\Debouncer;
use Padmission\Tickets\Enums\NotificationStrategy;
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

    public function handle(
        TicketActivityEvent|TicketAssignedEvent|TicketClosedEvent|TicketCreatedEvent $event
    ): void {
        $recipients = $this->recipientService->getNotificationRecipients($event);

        $recipients->each(
            fn (Authenticatable $user) => $this->sendNotificationToUser($user, $event)
        );
    }

    protected function getNotificationType($event): ?string
    {
        $type = str($event::class)
            ->classBasename()
            ->after('Ticket')
            ->beforeLast('Event')
            ->lower()
            ->toString();

        return $type ?: null;
    }

    protected function sendNotificationToUser(Authenticatable $user, $event): void
    {
        $strategy = $this->recipientService->getUserNotificationStrategy($user);

        if ($strategy === NotificationStrategy::Immediate) {
            $this->dispatchNotificationJob($user, $event->ticket, $event);
        } else {
            $this->dispatchDebouncedNotification($user, $event->ticket, $event);
        }
    }

    protected function dispatchNotificationJob(Authenticatable $user, Ticket $ticket, $event): void
    {
        $jobClass = TicketPlugin::resolveJobClass(NotificationJob::class);
        $jobClass::dispatch($user, $ticket, $event);
    }

    /**
     * Dispatch a debounced notification
     *
     * This method uses the Laravel Queue Debouncer to ensure that:
     * 1. If no notification job exists for this user-ticket combination, schedule one for 5 minutes
     * 2. If a job already exists, cancel it and schedule a new one (resets the timer)
     * 3. This ensures active conversations don't trigger emails until 5 minutes of silence
     */
    protected function dispatchDebouncedNotification(Authenticatable $user, Ticket $ticket, $event): void
    {
        $debounceTimeInSeconds = config('padmission-tickets.notification-debounce', CarbonInterval::minutes(5)->totalSeconds);
        $jobClass = TicketPlugin::resolveJobClass(NotificationJob::class);
        $job = new $jobClass($user, $ticket, $event);

        $debouncer = resolve(Debouncer::class);

        $debouncer
            ->usingCacheKeyProvider(fn () => $job->uniqueId())
            ->debounce($job, $debounceTimeInSeconds);
    }
}
