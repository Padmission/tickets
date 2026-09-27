<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

use function app;

class ReassignTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reassign-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (Ticket $record): string => $record->assignee_id
                ? __('padmission-tickets::tickets.actions.reassign.label')
                : __('padmission-tickets::tickets.actions.reassign.label_unassigned'))
            ->modalHeading(__('padmission-tickets::tickets.actions.reassign.modal_heading'))
            ->modalDescription(fn (Ticket $record): string => static::describe($record))
            ->modalSubmitActionLabel(__('padmission-tickets::tickets.actions.reassign.submit'))
            ->modalSubmitAction(fn (Action $action, Ticket $record) => static::choices($record) === [] ? false : $action)
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->modalWidth(Width::Medium)
            ->hidden(fn (Ticket $record): bool => $record->isNotInCurrentPanel() || $record->isClosed)
            ->schema([
                Text::make(__('padmission-tickets::tickets.actions.reassign.nobody_to_assign'))
                    ->visible(fn (Ticket $record): bool => static::choices($record) === []),

                Text::make(fn (): string => TicketPlugin::get()->getAssignableUsersDescription()
                    ?? __('padmission-tickets::tickets.actions.reassign.who_can_be_assigned'))
                    ->color('gray')
                    ->visible(fn (Ticket $record): bool => static::choices($record) === []),

                Select::make('assignee_id')
                    ->label(__('padmission-tickets::tickets.actions.reassign.new_assignee'))
                    ->options(fn (Ticket $record): array => static::choices($record))
                    ->visible(fn (Ticket $record): bool => static::choices($record) !== [])
                    ->searchable()
                    ->required()
                    ->hintAction(
                        Action::make('assign-to-me')
                            ->label(__('padmission-tickets::tickets.actions.reassign.assign_to_me'))
                            ->visible(fn (Ticket $record): bool => static::currentUserChoiceId($record) !== null)
                            ->action(fn (Set $set, Ticket $record) => $set('assignee_id', static::currentUserChoiceId($record))),
                    ),
            ])
            ->action(function (Ticket $record, array $data, Component $livewire): void {
                if (! array_key_exists($data['assignee_id'] ?? '', static::choices($record))) {
                    $this->failureNotificationTitle(__('padmission-tickets::tickets.resources.tickets.invalid_assignee'));
                    $this->failure();

                    return;
                }

                $record->update(['assignee_id' => $data['assignee_id']]);

                $livewire->dispatch('refresh-sidebar');

                $this->success();
            })
            ->successNotificationTitle(__('padmission-tickets::tickets.actions.reassign.success'));
    }

    protected static function describe(Ticket $record): string
    {
        $sentences = [__('padmission-tickets::tickets.actions.reassign.modal_description')];

        if ($record->assignee !== null) {
            $sentences[] = __('padmission-tickets::tickets.actions.reassign.currently_assigned', [
                'name' => Filament::getUserName($record->assignee),
            ]);
        }

        if (CreateLinkedTicketAction::isAvailableFor($record)) {
            $sentences[] = TicketPlugin::teamText(
                'padmission-tickets::tickets.actions.reassign.modal_description_escalation',
                TicketPlugin::get()->getEscalationTargetName(),
            );
        }

        $sentences[] = static::describeEscalationOwner($record);

        return implode(' ', array_filter($sentences));
    }

    /*
     * Reassigning moves the conversation with the requester, not the one with
     * the other team: that follows whoever handles the escalation.
     */
    protected static function describeEscalationOwner(Ticket $record): ?string
    {
        $escalation = resolve(TicketEscalationLinks::class)->escalationOf($record);

        if ($escalation === null || $escalation->isClosed) {
            return null;
        }

        $key = 'padmission-tickets::tickets.actions.reassign.escalation_stays';
        $team = TicketPlugin::find($escalation->panel)?->getSupportTeamName();

        if ($escalation->submitter_id === Filament::auth()->id()) {
            return TicketPlugin::teamText("{$key}_you", $team);
        }

        return $escalation->submitter === null
            ? null
            : TicketPlugin::teamText($key, $team, ['name' => resolve(GetUserDisplayName::class)->forUser($escalation->submitter)]);
    }

    /**
     * The ticket goes to the supporters query so a host can scope it to the
     * ticket, such as its tenant, rather than to whoever is looking.
     *
     * @return array<int|string, string>
     */
    protected static function choices(Ticket $record): array
    {
        $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

        if (! $allSupportersQuery) {
            return [];
        }

        return app()->call($allSupportersQuery, ['ticket' => $record])
            ->pluck('name', 'id')
            ->except($record->assignee_id)
            ->all();
    }

    protected static function currentUserChoiceId(Ticket $record): int|string|null
    {
        $choiceIds = array_keys(static::choices($record));

        foreach (TicketPlugin::get()->getCurrentUserAssigneeIds() as $id) {
            if (in_array($id, $choiceIds)) {
                return $id;
            }
        }

        return null;
    }
}
