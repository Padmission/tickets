<?php

namespace Padmission\Tickets\ConfigurationManagers;

use BadMethodCallException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Padmission\Tickets\Configuration\Context\EventConfigurationContext;
use Padmission\Tickets\Configuration\Data\EventNotificationSettings;
use Padmission\Tickets\ConfigurationManagers\Concerns\HasDefaultNotificationSettings;
use Padmission\Tickets\Exceptions\InvalidEventException;
use Padmission\Tickets\Models\Ticket;

/**
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
        return new static;
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
        return Str::snake(substr($method, 2));
    }

    private function isValidEvent(string $event): bool
    {
	    $event = Str::start($event, 'ticket_');
        return array_key_exists($event, $this->getDefaultSettings());
    }

    private function getAvailableMethods(): array
    {
        return array_map(
            fn ($event) => 'on'.Str::studly($event),
            array_keys($this->getDefaultSettings())
        );
    }

    private function configureEventDynamically(
        string $event,
        array|callable|null $userTriggered = null,
        array|callable|null $supporterTriggered = null,
        ?callable $configurator = null
    ): static {
        if (is_callable($userTriggered) && $supporterTriggered === null && $configurator === null) {
            $reflection = new \ReflectionFunction($userTriggered);
            if ($reflection->getNumberOfParameters() > 1) {
                return $this->storeCallbackForLaterEvaluation($event, $userTriggered);
            }

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

    private function configureEventWithCallbackAndContext(string $event, callable $callback, $ticketContext = null, $userContext = null): static
    {
        $context = new EventConfigurationContext($event, $this->getDefaultSettingsFor($event));

        $reflection = new \ReflectionFunction($callback);
        $paramCount = $reflection->getNumberOfParameters();

        if ($paramCount === 1) {
            $callback($context);
        } elseif ($paramCount === 2) {
            $callback($context, $ticketContext);
        } elseif ($paramCount >= 3) {
            $callback($context, $ticketContext, $userContext);
        }

        $this->eventSettings[$event] = $context->build();

        return $this;
    }

    public function getSettingsForWithContext(string $event, $ticketContext = null, $userContext = null): EventNotificationSettings
    {
        if (isset($this->eventCallbacks[$event])) {
            return $this->evaluateCallbackWithContext($event, $this->eventCallbacks[$event], $ticketContext, $userContext);
        }

        return $this->getSettingsFor($event);
    }

    private array $eventCallbacks = [];

    private function storeCallbackForLaterEvaluation(string $event, callable $callback): static
    {
        $this->eventCallbacks[$event] = $callback;

        $context = new EventConfigurationContext($event, $this->getDefaultSettingsFor($event));
        $callback($context);
        $this->eventSettings[$event] = $context->build();

        return $this;
    }

    private function evaluateCallbackWithContext(string $event, callable $callback, $ticketContext = null, $userContext = null): EventNotificationSettings
    {
        $context = new EventConfigurationContext($event, $this->getDefaultSettingsFor($event));

        $reflection = new \ReflectionFunction($callback);
        $paramCount = $reflection->getNumberOfParameters();

        if ($paramCount === 1) {
            $callback($context);
        } elseif ($paramCount === 2) {
            $callback($context, $ticketContext);
        } elseif ($paramCount >= 3) {
            $callback($context, $ticketContext, $userContext);
        }

        return $context->build();
    }

    public function getSettingsForTicketContext(string $event, Ticket $ticket, ?Authenticatable $actor = null): EventNotificationSettings
    {
        if (isset($this->eventCallbacks[$event])) {
            return $this->evaluateCallbackWithTicketContext($event, $this->eventCallbacks[$event], $ticket, $actor);
        }

        return $this->getSettingsFor($event);
    }

    public function getConfigurationForTrigger(string $event, Ticket $ticket, ?Authenticatable $actor = null): array
    {
        $settings = $this->getSettingsForTicketContext($event, $ticket, $actor);
        $triggerType = $this->getTriggerType($ticket, $actor);

        return $settings->getSettingsFor($triggerType);
    }

    /**
     * Determine trigger type based on actor's relationship to the ticket
     */
    protected function getTriggerType(Ticket $ticket, ?Authenticatable $actor): string
    {
        if (! $actor) {
            return 'supporter_triggered';
        }

        $actorId = $actor->getAuthIdentifier();

        if ($ticket->submitter_id === $actorId) {
            return 'user_triggered';
        }

        if ($ticket->assignee_id === $actorId) {
            return 'supporter_triggered';
        }

        return 'supporter_triggered';
    }

    private function evaluateCallbackWithTicketContext(string $event, callable $callback, Ticket $ticket, ?Authenticatable $actor): EventNotificationSettings
    {
        $context = new EventConfigurationContext($event, $this->getDefaultSettingsFor($event));

        $reflection = new \ReflectionFunction($callback);
        $paramCount = $reflection->getNumberOfParameters();

        if ($paramCount === 1) {
            $callback($context);
        } elseif ($paramCount === 2) {
            $callback($context, $ticket);
        } elseif ($paramCount >= 3) {
            $callback($context, $ticket, $actor);
        }

        return $context->build();
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
        if (! $this->isValidEvent($event)) {
	        dd($event);
            throw new InvalidEventException($event);
        }

        return $this->eventSettings[$event] ?? $this->getDefaultSettingsFor($event);
    }

    public function getAllSettings(): array
    {
        $allEvents = array_unique([
            ...array_keys($this->eventSettings),
            ...array_keys($this->getDefaultSettings()),
        ]);

        return collect($allEvents)
            ->mapWithKeys(fn ($event) => [$event => $this->getSettingsFor($event)])
            ->all();
    }

    public function extend(string $event, callable $callback): static
    {
        $current = $this->getSettingsFor($event);
        $this->eventSettings[$event] = $callback($current);

        return $this;
    }
}
