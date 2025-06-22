<?php

namespace Padmission\Tickets\ConfigurationManagers;

class NotificationConfiguration
{
    private array $rules = [
        'ticket_created' => [
            'user_triggered' => ['notify_user' => true, 'notify_supporter' => false],
            'supporter_triggered' => ['notify_user' => true, 'notify_supporter' => true],
        ],
        'ticket_assigned' => [
            'user_triggered' => ['notify_user' => false, 'notify_supporter' => false],
            'supporter_triggered' => ['notify_user' => false, 'notify_supporter' => true],
        ],
        'ticket_activity' => [
            'user_triggered' => ['notify_user' => false, 'notify_supporter' => true],
            'supporter_triggered' => ['notify_user' => true, 'notify_supporter' => false],
        ],
        'ticket_closed' => [
            'user_triggered' => ['notify_user' => true, 'notify_supporter' => false],
            'supporter_triggered' => ['notify_user' => true, 'notify_supporter' => false],
        ],
    ];

    public static function make(): static
    {
        return new static;
    }

    public function onTicketCreated(?array $userTriggered = null, ?array $supporterTriggered = null): static
    {
        if ($userTriggered !== null) {
            $this->rules['ticket_created']['user_triggered'] = $userTriggered;
        }
        if ($supporterTriggered !== null) {
            $this->rules['ticket_created']['supporter_triggered'] = $supporterTriggered;
        }

        return $this;
    }

    public function onTicketAssigned(?array $userTriggered = null, ?array $supporterTriggered = null): static
    {
        if ($userTriggered !== null) {
            $this->rules['ticket_assigned']['user_triggered'] = $userTriggered;
        }
        if ($supporterTriggered !== null) {
            $this->rules['ticket_assigned']['supporter_triggered'] = $supporterTriggered;
        }

        return $this;
    }

    public function onTicketActivity(?array $userTriggered = null, ?array $supporterTriggered = null): static
    {
        if ($userTriggered !== null) {
            $this->rules['ticket_activity']['user_triggered'] = $userTriggered;
        }        if ($supporterTriggered !== null) {
            $this->rules['ticket_activity']['supporter_triggered'] = $supporterTriggered;
        }

        return $this;
    }

    public function onTicketClosed(?array $userTriggered = null, ?array $supporterTriggered = null): static
    {
        if ($userTriggered !== null) {
            $this->rules['ticket_closed']['user_triggered'] = $userTriggered;
        }
        if ($supporterTriggered !== null) {
            $this->rules['ticket_closed']['supporter_triggered'] = $supporterTriggered;
        }

        return $this;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getConfigurationFor(string $event, string $triggerType): array
    {
        return $this->rules[$event][$triggerType] ?? [];
    }
}
