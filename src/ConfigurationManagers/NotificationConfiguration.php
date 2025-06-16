<?php

namespace Padmission\Tickets\ConfigurationManagers;

use BadMethodCallException;
use Illuminate\Support\Str;
use Padmission\Tickets\Configuration\Data\EventNotificationSettings;
use Padmission\Tickets\Configuration\Context\EventConfigurationContext;
use Padmission\Tickets\ConfigurationManagers\Concerns\HasDefaultNotificationSettings;
use PHPUnit\Event\InvalidEventException;


/**
 *
 * $config = NotificationConfiguration::make()
 * ->onTicketCreated(
 * userTriggered: ['notify_user' => true, 'notify_supporter' => false],
 * supporterTriggered: ['notify_user' => true, 'notify_supporter' => true]
 * );
 *
 * $config = NotificationConfiguration::make()
 * ->onTicketCreated(function (EventConfigurationContext $context) {
 * $context->whenUserTriggered(['notify_user' => true])
 * ->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => true]);
 * });
 *
 * $config = NotificationConfiguration::make()
 * ->onTicketCreated(function (EventConfigurationContext $context) {
 * $context->notifyUser()
 * ->whenSupporterTriggered(fn($defaults) => [...$defaults, 'notify_manager' => true]);
 * });
 *
 * $config = NotificationConfiguration::make()
 * ->onTicketCreated(
 * userTriggered: fn($defaults) => [...$defaults, 'send_email' => true],
 * supporterTriggered: ['notify_supporter' => true]
 * );
 */


/**
 * @method static onTicketCreated(array|callable|null $userTriggered = null, array|callable|null $supporterTriggered = null, ?callable $configurator = null)
 * @method static onTicketAssigned(array|callable|null $userTriggered = null, array|callable|null $supporterTriggered = null, ?callable $configurator = null)
 * @method static onTicketActivity(array|callable|null $userTriggered = null, array|callable|null $supporterTriggered = null, ?callable $configurator = null)
 * @method static onTicketClosed(array|callable|null $userTriggered = null, array|callable|null $supporterTriggered = null, ?callable $configurator = null)
 */
class NotificationConfiguration
{
    use HasDefaultNotificationSettings;

    /** @var array<string, EventNotificationSettings> */
    private array $eventSettings = [];

    public static function make(): static
    {
        return new static();
    }

    public function __call(string $method, array $arguments): static
    {
        if (Str::startsWith($method, 'on') && strlen($method) > 2) {
            $event = $this->methodToEventName($method);
            return $this->configureEventDynamically($event, ...$arguments);
        }

        throw new BadMethodCallException("Method {$method} does not exist");
    }

    private function methodToEventName(string $method): string
    {
        return Str::snake(substr($method, 2)); // onTicketCreated -> ticket_created
    }

    private function isValidEvent(string $event): bool
    {
        return array_key_exists($event, $this->getDefaultSettings());
    }

    private function getAvailableMethods(): array
    {
        return array_map(
            fn($event) => 'on' . Str::studly($event),
            array_keys($this->getDefaultSettings())
        );
    }

    private function configureEventDynamically(
        string $event,
        array|callable|null $userTriggered = null,
        array|callable|null $supporterTriggered = null,
        ?callable $configurator = null
    ): static {
        // This is your existing logic from the individual methods
        if (is_callable($userTriggered) && $supporterTriggered === null && $configurator === null) {
            return $this->configureEventWithCallback($event, $userTriggered);
        }

        return $this->configureEvent($event, $userTriggered, $supporterTriggered);
    }

    private function configureEvent(
        string $event,
        array|callable|null $userTriggered,
        array|callable|null $supporterTriggered
    ): static {
        $defaults = $this->getDefaultSettingsFor($event);

        $this->eventSettings[$event] = new EventNotificationSettings(
            userTriggered: $this->resolveConfiguration($userTriggered, $defaults->userTriggered),
            supporterTriggered: $this->resolveConfiguration($supporterTriggered, $defaults->supporterTriggered),
        );

        return $this;
    }

    private function configureEventWithCallback(string $event, callable $callback): static
    {
        $context = new EventConfigurationContext($event, $this->getDefaultSettingsFor($event));
        $callback($context);

        $this->eventSettings[$event] = $context->build();

        return $this;
    }

    private function resolveConfiguration(array|callable|null $config, array $defaults): array
    {
        if (is_callable($config)) {
            return $config($defaults);
        }

        return $config ?? $defaults;
    }

    public function getSettingsFor(string $event): EventNotificationSettings
    {
        if (!$this->isValidEvent($event)) {
            throw new InvalidEventException($event);
        }
        return $this->eventSettings[$event] ?? $this->getDefaultSettingsFor($event);
    }

    public function getAllSettings(): array
    {
        // Merge configured settings with defaults
        $allEvents = array_unique([
            ...array_keys($this->eventSettings),
            ...array_keys($this->getDefaultSettings())
        ]);

        return collect($allEvents)
            ->mapWithKeys(fn($event) => [$event => $this->getSettingsFor($event)])
            ->all();
    }

    public function extend(string $event, callable $callback): static
    {
        $current = $this->getSettingsFor($event);
        $this->eventSettings[$event] = $callback($current);

        return $this;
    }
}
