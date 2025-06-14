<?php

namespace Padmission\Tickets\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mpbarlow\LaravelQueueDebouncer\Traits\Debounceable;
use Padmission\Tickets\Models\Contracts\TicketInterface;
use Padmission\Tickets\TicketPlugin;

class NotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Debounceable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string|int $userId;

    protected string $modelType;

    protected string|int $modelId;

    public function __construct(
        Authenticatable $user,
        TicketInterface $model,
        public string $notificationType
    ) {
        $this->userId = $user->getKey();
        $this->modelType = get_class($model);
        $this->modelId = $model->getKey();
    }

    public function handle(): void
    {
        try {
            $notificationClass = $this->getNotificationClass();

            if (! $notificationClass) {
                return;
            }

            $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);

            /** @var object $user */
            $user = $userModel::find($this->userId);

            if (! $user) {
                return;
            }

            $model = $this->modelType;
            $record = $model::find($this->modelId);

            if (! $record) {
                return;
            }

            $user->notify(new $notificationClass($record));
        } catch (\Exception $e) {
        }
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
        return $this->modelType.'-'.$this->modelId.'-'.$this->userId;
    }
}
