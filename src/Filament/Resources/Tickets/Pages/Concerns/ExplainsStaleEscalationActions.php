<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Throwable;

/*
 * Escalating, adding to an escalation, handing it over and taking it over are
 * offered only while the ticket is in the state they need. When that state
 * changed while the dialog was open, Filament finds the action no longer
 * offered and refuses it without a word, so the page says what happened.
 */
trait ExplainsStaleEscalationActions
{
    /**
     * The action the request came to submit, read before Filament drops one it
     * can no longer find while booting.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $submittedEscalationAction = null;

    public function hydrateExplainsStaleEscalationActions(): void
    {
        $mounted = Arr::last($this->mountedActions);

        $this->submittedEscalationAction = is_array($mounted) ? $mounted : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callMountedAction(array $arguments = []): mixed
    {
        $refusal = $this->staleEscalationRefusal();

        if ($refusal === null) {
            return parent::callMountedAction($arguments);
        }

        Notification::make()
            ->danger()
            ->title($refusal['title'])
            ->body($refusal['body'])
            ->send();

        $this->mountedActions = array_values(array_filter($this->mountedActions, fn (mixed $mounted): bool => is_array($mounted) && filled($mounted['name'] ?? null)));

        if ($this->mountedActions !== []) {
            $this->unmountAction();
        }

        return null;
    }

    /**
     * @return array{title: string, body: ?string}|null
     */
    protected function staleEscalationRefusal(): ?array
    {
        $current = Arr::last($this->mountedActions);
        // Dropped while the page booted: at most the submitted form data is left where it was.
        $dropped = ! is_array($current) || blank($current['name'] ?? null);
        $mounted = $dropped ? $this->submittedEscalationAction : $current;
        $name = is_array($mounted) ? ($mounted['name'] ?? null) : null;

        if (! in_array($name, [...static::STALE_HAND_OVER_ACTIONS, ...static::STALE_LINK_ACTIONS], true)) {
            return null;
        }

        $action = $dropped ? null : rescue(fn (): ?Action => $this->getMountedAction(), report: false);

        if ($action instanceof Action && ! $action->isDisabled()) {
            return null;
        }

        $record = $this->staleEscalationRecord($action, $mounted);

        if (! $record instanceof Ticket) {
            return null;
        }

        if (in_array($name, static::STALE_HAND_OVER_ACTIONS, true)) {
            return ['title' => __('padmission-tickets::tickets.actions.hand_over.refused'), 'body' => null];
        }

        // Only a ticket the viewer may still edit is refused for its state, not for their rights.
        if (! TicketResource::canEdit($record)) {
            return null;
        }

        $key = 'padmission-tickets::tickets.resources.tickets.link_refused';

        return [
            'title' => __("{$key}.title"),
            'body' => __(resolve(TicketEscalationLinks::class)->hasOpenEscalation($record) ? "{$key}.already_escalated" : "{$key}.not_linkable"),
        ];
    }

    /**
     * @param  array<string, mixed>  $mounted
     */
    protected function staleEscalationRecord(?Action $action, array $mounted): ?Ticket
    {
        $record = $action?->getRecord();

        if ($record instanceof Ticket) {
            return $record->refresh();
        }

        $key = $mounted['context']['recordKey'] ?? null;

        if (filled($key)) {
            return TicketResource::getEloquentQuery()->find($key);
        }

        try {
            $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
        } catch (Throwable) {
            return null;
        }

        return $record instanceof Ticket ? $record->refresh() : null;
    }

    /** @var list<string> */
    protected const array STALE_HAND_OVER_ACTIONS = ['hand-over-escalation', 'take-over-escalation', 'takeOver'];

    /** @var list<string> */
    protected const array STALE_LINK_ACTIONS = ['create-linked-ticket', 'add-to-escalation'];
}
