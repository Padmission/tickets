<?php

namespace Padmission\Tickets\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Mpbarlow\LaravelQueueDebouncer\Traits\Debounceable;

class NotificationJob implements ShouldQueue, ShouldBeUnique
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
        Model $model,
        public string $notificationType
    ) {
        $this->userId = $user->getKey();
        $this->modelType = get_class($model);
        $this->modelId = $model->getKey();
    }

    public function handle(): void
    {
        /**
         * TODO: I didn't want to keep all of the models serialized to help us with our queue memory.
         * We need to move these to use the correct models.
         */
        try {
            $notificationClass = $this->getNotificationClass();

            if (!$notificationClass) {
                return;
            }

            $user = User::find($this->userId);

            $model = $this->modelType;
            $record = $model::find($this->modelId);

            $user->notify(new $notificationClass($record));

            logger("Debounced job executed: ".$this->uniqueId());
        } catch (\Exception $e) {

        }

    }

    protected function getNotificationClass() : string|null {
        $notifications = config('padmission-tickets.notifications', []);
        if (!array_key_exists($this->notificationType, $notifications)) {
            return null;
        }
        if (!$notifications[$this->notificationType]) {
            return null;
        }
        return $notifications[$this->notificationType];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string {
        return $this->modelType.'-'.$this->modelId.'-'.$this->userId;
    }
}
