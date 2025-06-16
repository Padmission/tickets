<?php

namespace Padmission\Tickets\Configuration\Context;

use Padmission\Tickets\Configuration\Data\EventNotificationSettings;

class EventConfigurationContext
{
    private array $userTriggered;
    private array $supporterTriggered;

    public function __construct(
        private string $event,
        EventNotificationSettings $defaults
    ) {
        $this->userTriggered = $defaults->userTriggered;
        $this->supporterTriggered = $defaults->supporterTriggered;
    }

    public function whenUserTriggered(array|callable $config): static
    {
        $this->userTriggered = is_callable($config)
            ? $config($this->userTriggered)
            : $config;

        return $this;
    }

    public function whenSupporterTriggered(array|callable $config): static
    {
        $this->supporterTriggered = is_callable($config)
            ? $config($this->supporterTriggered)
            : $config;

        return $this;
    }

    public function notifyUser(bool $notify = true): static
    {
        $this->userTriggered['notify_user'] = $notify;
        $this->supporterTriggered['notify_user'] = $notify;
        return $this;
    }

    public function notifySupporter(bool $notify = true): static
    {
        $this->userTriggered['notify_supporter'] = $notify;
        $this->supporterTriggered['notify_supporter'] = $notify;
        return $this;
    }

    public function onlyNotifyUser(): static
    {
        $this->userTriggered = ['notify_user' => true, 'notify_supporter' => false];
        $this->supporterTriggered = ['notify_user' => true, 'notify_supporter' => false];
        return $this;
    }

    public function onlyNotifySupporter(): static
    {
        $this->userTriggered = ['notify_user' => false, 'notify_supporter' => true];
        $this->supporterTriggered = ['notify_user' => false, 'notify_supporter' => true];
        return $this;
    }

    public function notifyBoth(): static
    {
        $this->userTriggered = ['notify_user' => true, 'notify_supporter' => true];
        $this->supporterTriggered = ['notify_user' => true, 'notify_supporter' => true];
        return $this;
    }

    public function notifyNone(): static
    {
        $this->userTriggered = ['notify_user' => false, 'notify_supporter' => false];
        $this->supporterTriggered = ['notify_user' => false, 'notify_supporter' => false];
        return $this;
    }

    public function build(): EventNotificationSettings
    {
        return new EventNotificationSettings(
            userTriggered: $this->userTriggered,
            supporterTriggered: $this->supporterTriggered
        );
    }
}
