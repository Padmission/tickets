<?php

namespace Padmission\Tickets\Models\Traits;

trait HasNotificationStrategy
{
    public function getNotificationStrategy($notification): string
    {
        // Check if user has specific strategy preference for this notification type
        $notificationClass = get_class($notification);

        return $this->notificationSettings()
            ->where('notification_type', $notificationClass)
            ->value('strategy') ?? 'immediate';
    }

    public function notificationSettings()
    {
        dd(__LINE__);

        return $this->hasMany(\YourVendor\NotificationPackage\Models\NotificationSetting::class);
    }

    public function setNotificationStrategy(string $notificationClass, string $strategy): void
    {
        $this->notificationSettings()->updateOrCreate([
            'notification_type' => $notificationClass,
        ], [
            'strategy' => $strategy,
        ]);
    }
}
