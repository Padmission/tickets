<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use ArrayObject;
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

    /**
     * Per record, whether the dialog is a hand over and who it offers, read
     * once a request. The dialog is drawn again after the move, which would
     * otherwise show the opposite dialog while the browser leaves or it
     * closes. Filament clones the action per record, and the clones share it.
     *
     * @var ArrayObject<string, array{owner: bool, choices?: array<int|string, string>}>
     */
    protected ArrayObject $dialogs;

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

        $this->dialogs = new ArrayObject;

        $key = 'padmission-tickets::tickets.actions.';

        // Filament evaluates these on a clone of the action per record, so they
        // read the action they are handed rather than $this.
        $this
            ->label(fn (self $action): string => __($action->ownsEscalation() ? $key.'hand_over.label' : $key.'hand_over.take_over_label'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->authorize(fn (self $action): bool => static::isAvailableFor($action->getEscalation()))
            ->requiresConfirmation(fn (self $action): bool => ! $action->viewerIsOwner())
            // A centred dialog, as Reassign is, whatever a host makes actions default to; Take over is a centred confirm.
            ->slideOver(false)
            ->modalIcon(fn (self $action): ?Heroicon => $action->viewerIsOwner() ? null : Heroicon::OutlinedArrowsRightLeft)
            ->modalWidth(Width::Medium)
            ->extraModalWindowAttributes(fn (self $action): array => $action->leavesPage() ? ['x-on:click.capture' => self::LEAVING_GUARD] : [])
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

    /*
     * The owner's hand over leaves the page, which then refuses them. From the
     * moment it is submitted until the answer comes back the dialog takes no
     * Escape or clicks, since closing it would reach the page after the move;
     * it is let go again when the answer does not leave the page.
     */
    protected const string LEAVING_GUARD = <<<'JS'
        (() => {
        if (! $event.target.closest('button[type=submit]') || window.padTiLeaving) return;
        window.padTiLeaving = true;
        const block = (event) => event.key === 'Escape' && event.stopImmediatePropagation();
        const modals = [...document.querySelectorAll('.fi-modal')];
        const release = () => { window.padTiLeaving = false; document.removeEventListener('keydown', block, true); modals.forEach((modal) => modal.inert = false); };
        document.addEventListener('keydown', block, true);
        setTimeout(() => modals.forEach((modal) => modal.inert = true));
        const stop = Livewire.hook('request', ({ succeed, fail }) => {
            succeed(({ json }) => { if (! (json?.components ?? []).some((component) => component.effects?.redirect)) release(); stop?.(); });
            fail(() => { release(); stop?.(); });
        });
        })()
        JS;

    public function getModalSubmitAction(): ?Action
    {
        if ($this->viewerIsOwner() && $this->choices() === []) {
            return null;
        }

        return parent::getModalSubmitAction();
    }

    protected function leavesPage(): bool
    {
        return $this->viewerIsOwner() && ! $this->getLivewire() instanceof ListTickets;
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
        $this->viewerIsOwner();
        $this->choices();
        $isOwner = $this->ownsEscalation();
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
                ? __('padmission-tickets::tickets.actions.hand_over.success', ['name' => e($toName)])
                : __('padmission-tickets::tickets.actions.take_over.success'))
            ->send();

        // The previous owner can no longer open the escalation.
        if ($isOwner && ! $livewire instanceof ListTickets) {
            $this->redirect(TicketResource::getUrl('index', ['tab' => 'linked']));

            // Unmounting the dialog would empty its owner field, which then asks
            // this page for a label before the browser leaves, as someone the
            // page now refuses. The hand over is already done and kept. Closing
            // the dialog, by Escape or its buttons, is another such request, so
            // until the browser leaves the dialog takes no input at all.
            $livewire->js(<<<'JS'
                document.addEventListener('keydown', (event) => event.key === 'Escape' && event.stopImmediatePropagation(), true);
                document.querySelectorAll('.fi-modal').forEach((modal) => modal.inert = true);
                document.querySelectorAll('chat-component').forEach((chat) => chat.stopPolling?.());
                JS);

            $this->halt();
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

    /*
     * Whether this request's dialog is a hand over, settled the first time
     * it is asked. What the move itself does is decided by ownsEscalation().
     */
    protected function viewerIsOwner(): bool
    {
        return $this->dialog()['owner'];
    }

    protected function ownsEscalation(): bool
    {
        $escalation = $this->getEscalation();

        return $escalation?->isSubmittedBy(Filament::auth()->id()) ?? false;
    }

    /**
     * @return array{owner: bool, choices?: array<int|string, string>}
     */
    protected function dialog(): array
    {
        $key = $this->dialogKey();

        if (! $this->dialogs->offsetExists($key)) {
            $this->dialogs[$key] = ['owner' => $this->ownsEscalation()];
        }

        return $this->dialogs[$key];
    }

    protected function dialogKey(): string
    {
        $record = $this->getRecord();

        return $record instanceof Ticket ? (string) $record->getKey() : '';
    }

    /**
     * The escalating team's supporters, found for this escalation so a host
     * can pin them to its tenant.
     *
     * @return array<int|string, string>
     */
    protected function choices(): array
    {
        $dialog = $this->dialog();

        if (array_key_exists('choices', $dialog)) {
            return $dialog['choices'];
        }

        return ($this->dialogs[$this->dialogKey()] = [...$dialog, 'choices' => $this->findChoices()])['choices'];
    }

    /**
     * @return array<int|string, string>
     */
    protected function findChoices(): array
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
