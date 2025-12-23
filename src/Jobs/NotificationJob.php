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
use Illuminate\Support\Facades\Notification;
use Mpbarlow\LaravelQueueDebouncer\Traits\Debounceable;
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

    public function __construct(
        Authenticatable $user,
        Ticket $model,
        public $event
    ) {
        $this->userId = $user->getKey();
        $this->ticketClass = get_class($model);
        $this->ticketKey = $model->getKey();
    }

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

        if (! $record) {
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

    protected function sendNotification(Model $user, Ticket $record, string $notificationClass): void
    {
        Notification::send($user, new $notificationClass($record, $this->event));
    }

    protected function getNotificationClass(): ?string
    {
        $notifications = config('padmission-tickets.notifications', []);
        $eventClass = $this->event::class;

        if (array_key_exists($eventClass, $notifications)) {
            return $notifications[$eventClass];
        }

        return null;
    }

    /**
     * Build the unique ID for this job (can be overridden for custom logic)
     *
     * Note: We only use ticket-user combination for the unique ID to ensure
     * that new activities for the same ticket-user pair will replace existing
     * debounced notifications, which is exactly what we want for debouncing.
     */
    public function uniqueId(): string
    {
        return "notification-{$this->ticketClass}-{$this->ticketKey}-{$this->userId}";
    }
}
