<?php

namespace Padmission\Tickets\Channels;


use Illuminate\Notifications\Notification;
use Padmission\Tickets\Managers\NotificationManager;

class StrategicNotificationChannel
{
    public function __construct(protected NotificationManager $manager)
    {
    }

    public function send($notifiable, Notification $notification)
    {
        // Check if notification has a strategy set
        $strategy = $notification->strategy ?? $notifiable->getNotificationStrategy($notification) ?? 'immediate';

        // Parse strategy (e.g., "delayed:30" or "end_of_day:18:00")
        [$driverName, $params] = $this->parseStrategy($strategy);

        return $this->manager
            ->driver($driverName)
            ->sendNotification($notifiable, $notification, $params);
    }

    protected function parseStrategy(string $strategy): array
    {
        if (str_contains($strategy, ':')) {
            [$driver, $params] = explode(':', $strategy, 2);
            return [$driver, $params];
        }

        return [$strategy, null];
    }
}
