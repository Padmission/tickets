<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

/*
 * The owner hands the escalation to a colleague, and anyone else on the team
 * takes it over. Either way the other team's replies, access to the
 * escalation and My Escalations all follow its submitter.
 */
class HandOverEscalationAction extends Action
{
    protected ?Closure $escalationUsing = null;

    /** @var array<string, ?Ticket> */
    protected array $escalations = [];

    public static function getDefaultName(): ?string
    {
        return 'hand-over-escalation';
    }

    /*
     * Offered on an original for its escalation.
     */
    public function escalationUsing(?Closure $callback): static
    {
        $this->escalationUsing = $callback;

        return $this;
    }

    public function getEscalation(): ?Ticket
    {
        $record = $this->getRecord();
        $key = $record instanceof Ticket ? (string) $record->getKey() : '';

        if (! array_key_exists($key, $this->escalations)) {
            $escalation = $this->escalationUsing === null ? $record : $this->evaluate($this->escalationUsing);

            $this->escalations[$key] = $escalation instanceof Ticket ? $escalation : null;
        }

        return $this->escalations[$key];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $key = 'padmission-tickets::tickets.actions.';

        // Hand over keeps the host's dialog style; Take over is always a centred confirm.
        $hostSlideOver = $this->isModalSlideOver;

        // Filament evaluates these on a clone of the action per record, so they
        // read the action they are handed rather than $this.
        $this
            ->label(fn (self $action): string => __($action->viewerIsOwner() ? $key.'hand_over.label' : $key.'hand_over.take_over_label'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (self $action): bool => static::isAvailableFor($action->getEscalation()))
            ->requiresConfirmation(fn (self $action): bool => ! $action->viewerIsOwner())
            ->slideOver(fn (self $action): bool => $action->viewerIsOwner() && (bool) $action->evaluate($hostSlideOver))
            ->modalIcon(fn (self $action): ?Heroicon => $action->viewerIsOwner() ? null : Heroicon::OutlinedArrowsRightLeft)
            ->modalWidth(Width::Medium)
            ->modalHeading(fn (self $action): string => __($action->viewerIsOwner() ? $key.'hand_over.modal_heading' : $key.'take_over.modal_heading'))
            ->modalDescription(fn (self $action): string => $action->viewerIsOwner()
                ? TicketPlugin::teamText($key.'hand_over.modal_description', $action->teamName())
                : TicketPlugin::teamText($key.'take_over.modal_description', $action->teamName(), ['name' => $action->ownerName()]))
            ->modalSubmitActionLabel(fn (self $action): string => __($action->viewerIsOwner() ? $key.'hand_over.submit' : $key.'take_over.submit'))
            ->fillForm(fn (self $action): array => ['expected_owner' => $action->getEscalation()?->submitter_id])
            ->schema(fn (self $action): array => [
                Hidden::make('expected_owner'),

                ...($action->viewerIsOwner() ? [
                    Text::make(__($key.'hand_over.nobody'))
                        ->visible(fn (): bool => $action->choices() === []),

                    Text::make(fn (): string => $action->sourcePlugin()?->getAssignableUsersDescription()
                        ?? __($key.'reassign.who_can_be_assigned'))
                        ->color('gray')
                        ->visible(fn (): bool => $action->choices() === []),

                    Select::make('new_owner')
                        ->label(__($key.'hand_over.new_owner'))
                        ->options(fn (): array => $action->choices())
                        ->visible(fn (): bool => $action->choices() !== [])
                        ->searchable()
                        ->required(),
                ] : []),
            ])
            ->action(fn (self $action, array $data, Component $livewire) => $action->handOver($data, $livewire));
    }

    public function getModalSubmitAction(): ?Action
    {
        return $this->viewerIsOwner() && $this->choices() === [] ? null : parent::getModalSubmitAction();
    }

    /*
     * Only an open escalation from this panel, read here by the team that
     * escalated it: the team it was sent to has its own Reassign.
     */
    public static function isAvailableFor(?Ticket $escalation): bool
    {
        return $escalation !== null
            && $escalation->isNotInCurrentPanel()
            && $escalation->isOpen
            && $escalation->isEscalationFrom(Filament::getCurrentOrDefaultPanel()->getId())
            && Gate::allows('handOver', $escalation);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handOver(array $data, Component $livewire): void
    {
        $escalation = $this->getEscalation();
        $isOwner = $this->viewerIsOwner();
        $to = $isOwner ? $this->choiceKey($data['new_owner'] ?? null) : Filament::auth()->id();

        if ($escalation === null || blank($to)) {
            $this->failureNotificationTitle(__('padmission-tickets::tickets.resources.tickets.invalid_assignee'));
            $this->failure();

            return;
        }

        $toName = $isOwner ? $this->choices()[$to] : null;

        $done = resolve(TicketEscalationLinks::class)->handOver($escalation, $to, $data['expected_owner'] ?? $escalation->submitter_id);

        if (! $done) {
            Notification::make()
                ->danger()
                ->title(__('padmission-tickets::tickets.actions.hand_over.refused'))
                ->send();

            // The dialog showed an owner that is no longer true.
            $this->cancel();
        }

        Notification::make()
            ->success()
            ->title($isOwner
                ? __('padmission-tickets::tickets.actions.hand_over.success', ['name' => $toName])
                : __('padmission-tickets::tickets.actions.take_over.success'))
            ->send();

        // The previous owner can no longer open the escalation.
        if ($isOwner && ! $livewire instanceof ListTickets) {
            $this->redirect(TicketResource::getUrl('index', ['tab' => 'linked']));
        }
    }

    /*
     * The chosen person as the option list keys them, so the new submitter
     * and the history note keep the key's own type rather than the form's
     * string. Null when the choice is not on the list.
     */
    protected function choiceKey(mixed $value): int|string|null
    {
        foreach (array_keys($this->choices()) as $key) {
            if ((string) $key === (string) $value) {
                return $key;
            }
        }

        return null;
    }

    protected function viewerIsOwner(): bool
    {
        $escalation = $this->getEscalation();

        return $escalation?->isSubmittedBy(Filament::auth()->id()) ?? false;
    }

    /**
     * The escalating team's supporters, found for this escalation so a host
     * can pin them to its tenant.
     *
     * @return array<int|string, string>
     */
    protected function choices(): array
    {
        $escalation = $this->getEscalation();
        $supportersQuery = $this->sourcePlugin()?->getAllSupportersQuery();

        if ($escalation === null || $supportersQuery === null) {
            return [];
        }

        return app()->call($supportersQuery, ['ticket' => $escalation])
            ->pluck('name', 'id')
            ->except($escalation->submitter_id)
            ->all();
    }

    protected function sourcePlugin(): ?TicketPlugin
    {
        $panel = $this->getEscalation()?->escalationSourcePanel();

        return $panel === null ? null : TicketPlugin::find($panel);
    }

    protected function teamName(): ?string
    {
        $escalation = $this->getEscalation();

        return $escalation === null ? null : TicketPlugin::find($escalation->panel)?->getSupportTeamName();
    }

    protected function ownerName(): string
    {
        $escalation = $this->getEscalation();

        return $escalation?->submitter !== null
            ? resolve(GetUserDisplayName::class)->forUser($escalation->submitter)
            : resolve(GetUserDisplayName::class)($escalation?->submitter_id);
    }
}
