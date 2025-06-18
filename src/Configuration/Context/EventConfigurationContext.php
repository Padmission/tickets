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

    public function enableChannel(string $channel, string $recipient = 'both'): static
    {
        $key = $recipient === 'both' ? "{$channel}_both" : "{$channel}_{$recipient}";

        if (in_array($recipient, ['user', 'both'])) {
            $this->userTriggered[$key] = true;
        }
        if (in_array($recipient, ['supporter', 'both'])) {
            $this->supporterTriggered[$key] = true;
        }

        return $this;
    }

    public function disableChannel(string $channel, string $recipient = 'both'): static
    {
        $key = $recipient === 'both' ? "{$channel}_both" : "{$channel}_{$recipient}";

        if (in_array($recipient, ['user', 'both'])) {
            $this->userTriggered[$key] = false;
        }
        if (in_array($recipient, ['supporter', 'both'])) {
            $this->supporterTriggered[$key] = false;
        }

        return $this;
    }

    public function viaChannels(array $channels, string $recipient = 'both'): static
    {
        foreach ($channels as $channel => $enabled) {
            if ($enabled) {
                $this->enableChannel($channel, $recipient);
            } else {
                $this->disableChannel($channel, $recipient);
            }
        }

        return $this;
    }

    public function when(callable|bool $condition, callable $callback): static
    {
        $shouldApply = is_callable($condition) ? $condition() : $condition;

        if ($shouldApply) {
            $callback($this);
        }

        return $this;
    }

    public function unless(callable|bool $condition, callable $callback): static
    {
        return $this->when(! $condition, $callback);
    }

    public function inEnvironment(string|array $environments, callable $callback): static
    {
        $currentEnv = app()->environment();
        $targetEnvs = is_array($environments) ? $environments : [$environments];

        return $this->when(
            in_array($currentEnv, $targetEnvs),
            $callback
        );
    }

    public function build(): EventNotificationSettings
    {
        return new EventNotificationSettings(
            userTriggered: $this->userTriggered,
            supporterTriggered: $this->supporterTriggered
        );
    }
}
