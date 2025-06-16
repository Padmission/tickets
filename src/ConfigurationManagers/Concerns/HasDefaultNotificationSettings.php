<?php

namespace Padmission\Tickets\ConfigurationManagers\Concerns;

use Padmission\Tickets\Configuration\Data\EventNotificationSettings;

trait HasDefaultNotificationSettings
{
    private array $defaultSettings = [
        'ticket_created' => [
            'userTriggered' => ['notify_user' => true, 'notify_supporter' => false],
            'supporterTriggered' => ['notify_user' => true, 'notify_supporter' => true],
        ],
        'ticket_assigned' => [
            'userTriggered' => [],
            'supporterTriggered' => ['notify_user' => false, 'notify_supporter' => true],
        ],
        'ticket_activity' => [
            'userTriggered' => ['notify_user' => false, 'notify_supporter' => true],
            'supporterTriggered' => ['notify_user' => true, 'notify_supporter' => false],
        ],
        'ticket_closed' => [
            'userTriggered' => ['notify_user' => true, 'notify_supporter' => false],
            'supporterTriggered' => ['notify_user' => true, 'notify_supporter' => false],
        ],
    ];

    protected function getDefaultSettingsFor(string $event): EventNotificationSettings
    {
        $defaults = $this->defaultSettings[$event] ?? [
            'userTriggered' => [],
            'supporterTriggered' => [],
        ];

        return new EventNotificationSettings(
            userTriggered: $defaults['userTriggered'],
            supporterTriggered: $defaults['supporterTriggered'],
        );
    }

    protected function getDefaultSettings(): array
    {
        return $this->defaultSettings;
    }

    public function setDefaultsFor(string $event, array $userTriggered, array $supporterTriggered): static
    {
        $this->defaultSettings[$event] = [
            'userTriggered' => $userTriggered,
            'supporterTriggered' => $supporterTriggered,
        ];

        return $this;
    }

    public function addDefaultEvent(string $event, array $userTriggered = [], array $supporterTriggered = []): static
    {
        $this->defaultSettings[$event] = [
            'userTriggered' => $userTriggered,
            'supporterTriggered' => $supporterTriggered,
        ];

        return $this;
    }
}
