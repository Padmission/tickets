<?php

namespace Padmission\Tickets\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Mpbarlow\LaravelQueueDebouncer\Traits\Debounceable;
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class NotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Debounceable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string|int $userId;

    protected string $ticketClass;

    protected string|int $ticketKey;

    public string $notificationType;

    public function __construct(
        Authenticatable $user,
        Ticket $model,
        public $event
    ) {
        $this->userId = $user->getKey();
        $this->ticketClass = get_class($model);
        $this->ticketKey = $model->getKey();
        $this->notificationType = str(is_object($this->event) ? get_class($this->event) : $this->event)
            ->afterLast('\\')
            ->replace('Ticket', '')
            ->replace('Event', '')
            ->lower()
            ->toString();

        $this->initializeJob($user, $model);
    }

    protected function initializeJob(Authenticatable $user, Ticket $model): void {}

    public function handle(): void
    {
        $notificationClass = $this->getNotificationClass();

        if (! $notificationClass) {
            return;
        }

        $user = $this->resolveUser();

        if (! $user) {
            return;
        }

        $record = $this->resolveModel();

        if (! $record || ! $this->stillConcerns($user, $record)) {
            return;
        }

        $this->sendNotification($user, $record, $notificationClass);
    }

    protected function resolveUser(): ?Model
    {
        $userModel = TicketPlugin::resolveUserModelClass();

        return $userModel::find($this->userId);
    }

    protected function resolveModel(): ?Ticket
    {
        $model = $this->ticketClass;

        return $model::find($this->ticketKey);
    }

    /*
     * Recipients were chosen when the event happened, and a debounced send
     * comes minutes later: by then the ticket may be someone else's, or no
     * longer theirs to see. A hand over decides for itself, since it goes to
     * the person it was taken from.
     */
    protected function stillConcerns(Model $user, Ticket $record): bool
    {
        if ($this->event instanceof TicketHandedOverEvent || $record->isSubmittedBy($user)) {
            return true;
        }

        if (filled($record->assignee_id) && (string) $record->assignee_id !== (string) $user->getKey()) {
            return false;
        }

        return Gate::forUser($user)->allows('view', $record);
    }

    protected function sendNotification(Model $user, Ticket $record, string $notificationClass): void
    {
        Notification::send($user, new $notificationClass($record, $this->event));
    }

    protected function getNotificationClass(): ?string
    {
        $notifications = config('padmission-tickets.notifications', []);
        $eventClass = get_class($this->event);

        if (array_key_exists($eventClass, $notifications)) {
            return $notifications[$eventClass];
        }

        return null;
    }

    /**
     * Build the unique ID for this job (can be overridden for custom logic)
     *
     * Note: The ticket-user combination coalesces repeated activity for the
     * same ticket-user pair into one debounced notification. The event type is
     * included so materially different events (created, assigned, closed)
     * cannot be silently dropped by a pending job for another event type.
     */
    public function uniqueId(): string
    {
        $id = "notification-{$this->ticketClass}-{$this->ticketKey}-{$this->userId}-{$this->notificationType}";

        // A take over and a hand back tell the same person different things, so neither may replace the other.
        if ($this->event instanceof TicketHandedOverEvent) {
            $id .= "-{$this->event->fromId}-{$this->event->toId}";
        }

        return $id;
    }

    public function getUserId(): string|int
    {
        return $this->userId;
    }

    public function getTicketClass(): string
    {
        return $this->ticketClass;
    }

    public function getTicketKey(): string|int
    {
        return $this->ticketKey;
    }
}
