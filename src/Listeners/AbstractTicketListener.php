<?php

namespace Padmission\Tickets\Listeners;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mpbarlow\LaravelQueueDebouncer\Facade\Debouncer;
use Padmission\Tickets\Events\TicketActivity;
use Padmission\Tickets\Events\TicketAssigned;
use Padmission\Tickets\Events\TicketClosed;
use Padmission\Tickets\Events\TicketCreated;
use Padmission\Tickets\Jobs\NotificationJob;

abstract class AbstractTicketListener
{
    public function handle(TicketActivity|TicketAssigned|TicketClosed|TicketCreated $event): void
    {
        collect([$event->ticket?->assignee, $event->ticket?->submitter])
            ->filter() // Remove null values
            ->unique()
            ->each(function (Authorizable $user) use ($event) {

                $type = strtolower(Str::chopStart(class_basename(get_class($event)), 'Ticket'));

                if (! $type) {
                    return;
                }

                $strategy = $this->getUserNotificationStrategy($user);

                if ($strategy === 'immediate') {
                    NotificationJob::dispatch($user, $event->ticket, $type);
                } else {
                    $debounceTime = config('padmission-tickets.notification-debounce', 300);
                    $job = new NotificationJob($user, $event->ticket, $type);

                    $jobId = $job->uniqueId();

                    if (Cache::has($jobId)) {
                        return;
                    }
                    Cache::put($jobId, true, 1);

                    Debouncer::usingCacheKeyProvider(fn () => $job->uniqueId())
                        ->debounce($job, $debounceTime);
                }
            });
    }

    protected function getUserNotificationStrategy(Authorizable $user): string
    {
        if (method_exists($user, 'ticketNotificationStrategy')) {
            return $user->ticketNotificationStrategy();
        }

        return 'debounced';
    }
}
