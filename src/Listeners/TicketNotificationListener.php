<?php

namespace Padmission\Tickets\Listeners;

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
        $notificationType = $this->getNotificationType($event);

        if (! $notificationType) {
            return;
        }

        $recipients->each(function (Authenticatable $user) use ($event, $notificationType) {
            $this->sendNotificationToUser($user, $event, $notificationType);
        });
    }

    protected function getNotificationType($event): ?string
    {
        $type = str($event::class)
            ->classBasename()
            ->lower()
            ->after('Ticket')
            ->beforeLast('Event')
            ->toString();

        return $type ?: null;
    }

    protected function sendNotificationToUser(Authenticatable $user, $event, string $type): void
    {
        $strategy = $this->recipientService->getUserNotificationStrategy($user);

        if ($strategy === NotificationStrategy::Immediate) {
            $this->dispatchNotificationJob($user, $event->ticket, $type);
        } else {
            $this->dispatchDebouncedNotification($user, $event->ticket, $type);
        }
    }

    protected function dispatchNotificationJob(Authenticatable $user, Ticket $ticket, string $type): void
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
    protected function dispatchDebouncedNotification(Authenticatable $user, Ticket $ticket, string $type): void
    {
        $debounceTime = config('padmission-tickets.notification-debounce', 300);
        $jobClass = TicketPlugin::resolveJobClass(NotificationJob::class);
        $job = new $jobClass($user, $ticket, $type);

        $debouncer = resolve(Debouncer::class);

        $debouncer
            ->usingCacheKeyProvider(fn () => $job->uniqueId())
            ->debounce($job, $debounceTime);
    }
}
