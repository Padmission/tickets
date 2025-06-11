<?php

namespace Padmission\Tickets\Listeners;

use Illuminate\Contracts\Auth\Access\Authorizable;
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
        collect([$event->ticket->assignee,$event->ticket->submitter])
            ->unique()
            ->map(function(Authorizable $user) use ($event) {

                $type = strtolower(Str::chopStart(class_basename(get_class($event)), 'Ticket'));

                if (!$type) {
                    return;
                }

                /**
                 * Send them the notification from the email that is debounced.
                 */
                $strategy = $user->ticketNotificationStrategy ?? 'debounced';


                if ($strategy === 'immediate') {
                    NotificationJob::dispatch($user, $event->ticket, $type);
                } else {
                    $job = new NotificationJob($user, $event->ticket, $type);
                    Debouncer::usingCacheKeyProvider(fn () => $job->uniqueId())
                        ->debounce($job, 5);
                }
            });
    }
}
