<?php

namespace Padmission\Tickets\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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
        public string $notificationType
    ) {
        $this->userId = $user->getKey();
        $this->ticketClass = get_class($model);
        $this->ticketKey = $model->getKey();

        $this->initializeJob($user, $model);
    }

    /**
     * Override this method to add custom initialization logic
     */
    protected function initializeJob(Authenticatable $user, Ticket $model): void
    {
        // Override in child classes for custom initialization
    }

    public function handle(): void
    {
        try {
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
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Resolve the user model
     */
    protected function resolveUser(): ?Authenticatable
    {
        $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);

        return $userModel::find($this->userId);
    }

    /**
     * Resolve the ticket model
     */
    protected function resolveModel(): ?Ticket
    {
        $model = $this->ticketClass;

        return $model::find($this->ticketKey);
    }

    /**
     * Send the notification
     */
    protected function sendNotification(Authenticatable $user, Ticket $record, string $notificationClass): void
    {
        $notification = new $notificationClass($record, $this->notificationType);
        NotificationFacade::sendNow($user, $notification);
    }

    /**
     * Handle exceptions that occur during job execution
     */
    protected function handleException(\Exception $e): void
    {
        Log::error($e->getMessage());
        // Override in child classes for custom error handling
        // Default behavior is to silently continue
    }

    protected function getNotificationClass(): ?string
    {
        $notifications = config('padmission-tickets.notifications', []);
        if (! array_key_exists($this->notificationType, $notifications)) {
            return null;
        }
        if (! $notifications[$this->notificationType]) {
            return null;
        }

        return $notifications[$this->notificationType];
    }

    public function uniqueId(): string
    {
        return $this->buildUniqueId();
    }

    /**
     * Build the unique ID for this job (can be overridden for custom logic)
     *
     * Note: We only use ticket-user combination for the unique ID to ensure
     * that new activities for the same ticket-user pair will replace existing
     * debounced notifications, which is exactly what we want for debouncing.
     */
    protected function buildUniqueId(): string
    {
        return "notification-{$this->ticketClass}-{$this->ticketKey}-{$this->userId}";
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
