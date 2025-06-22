<?php

namespace Padmission\Tickets\ConfigurationManagers;

use Closure;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationTrigger;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;

final class NotificationConfiguration
{
    protected array $config = [];

    public static function make(): static
    {
        return (new self)
            ->on(
                TicketCreatedEvent::class,
                fn (NotificationTrigger $trigger) => match ($trigger) {
                    NotificationTrigger::User => NotificationRecipient::User,
                    default => NotificationRecipient::Both,
                }
            )
            ->on(
                TicketAssignedEvent::class,
                fn (NotificationTrigger $trigger) => match ($trigger) {
                    NotificationTrigger::Supporter => NotificationRecipient::Supporter,
                    default => false,
                }
            )
            ->on(
                TicketActivityEvent::class,
                fn (NotificationTrigger $trigger) => match ($trigger) {
                    NotificationTrigger::User => NotificationRecipient::Supporter,
                    NotificationTrigger::Supporter => NotificationRecipient::User,
                }
            )
            ->on(
                TicketClosedEvent::class,
                fn (NotificationTrigger $trigger) => match ($trigger) {
                    NotificationTrigger::User => NotificationRecipient::Supporter,
                    NotificationTrigger::Supporter => NotificationRecipient::User,
                }
            );
    }

    /**
     * @param  class-string  $event
     */
    public function on(string $event, Closure $callback): static
    {
        $this->config[$event] = $callback;

        return $this;
    }

    public function getConfigurationFor(string $event, NotificationTrigger $triggerType): NotificationRecipient
    {
        if (! isset($this->config[$event])) {
            return NotificationRecipient::None;
        }

        return app()->call($this->config[$event], [
            'trigger' => $triggerType,
            'event' => $event,
        ]);
    }
}
