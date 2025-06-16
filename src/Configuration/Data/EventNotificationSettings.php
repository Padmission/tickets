<?php

namespace Padmission\Tickets\Configuration\Data;

class EventNotificationSettings
{
    public function __construct(
        public readonly array $userTriggered = [],
        public readonly array $supporterTriggered = [],
    ) {}

    public function merge(EventNotificationSettings $other): self
    {
        return new self(
            userTriggered: array_merge($this->userTriggered, $other->userTriggered),
            supporterTriggered: array_merge($this->supporterTriggered, $other->supporterTriggered),
        );
    }

    public function getSettingsFor(string $triggerType): array
    {
        return match ($triggerType) {
            'user_triggered' => $this->userTriggered,
            'supporter_triggered' => $this->supporterTriggered,
            default => [],
        };
    }

    public function toArray(): array
    {
        return [
            'user_triggered' => $this->userTriggered,
            'supporter_triggered' => $this->supporterTriggered,
        ];
    }
}
