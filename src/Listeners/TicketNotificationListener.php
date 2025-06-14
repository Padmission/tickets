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
use Padmission\Tickets\Models\Contracts\TicketInterface;
use Padmission\Tickets\Services\NotificationRecipientService;

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
            NotificationJob::dispatch($user, $event->ticket, $type);
        } else {
            $this->dispatchDebouncedNotification($user, $event->ticket, $type);
        }
    }

    /**
     * Dispatch a debounced notification
     */
    protected function dispatchDebouncedNotification(Authorizable $user, TicketInterface $ticket, string $type): void
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
}
