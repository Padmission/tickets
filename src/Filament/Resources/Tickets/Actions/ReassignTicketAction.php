<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;
use Padmission\Tickets\Actions\GetUserDisplayName;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Services\TicketReassignment;
use Padmission\Tickets\TicketPlugin;

class ReassignTicketAction extends Action
{
    protected const NOBODY = 'nobody';

    protected const ONLY_VIEWER = 'only_viewer';

    protected const ASSIGN = 'assign';

    protected const REASSIGN = 'reassign';

    public static function getDefaultName(): ?string
    {
        return 'reassign-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $key = 'padmission-tickets::tickets.actions.reassign.';

        $this
            ->label(fn (Ticket $record): string => $record->assignee_id
                ? __($key.'label')
                : __($key.'label_unassigned'))
            ->modalHeading(fn (Ticket $record): string => match (static::situation($record)) {
                self::NOBODY => __($key.'modal_heading_nobody'),
                self::REASSIGN => __($key.'modal_heading'),
                default => __($key.'modal_heading_unassigned'),
            })
            ->modalDescription(fn (Ticket $record): string => static::describe($record))
            ->modalSubmitActionLabel(fn (Ticket $record): string => match (static::situation($record)) {
                self::ONLY_VIEWER => __($key.'assign_to_me'),
                self::REASSIGN => __($key.'submit'),
                default => __($key.'submit_unassigned'),
            })
            ->modalSubmitAction(fn (Action $action, Ticket $record) => static::situation($record) === self::NOBODY ? false : $action)
            ->modalCancelActionLabel(fn (Ticket $record): ?string => static::situation($record) === self::NOBODY ? __($key.'close') : null)
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->slideOver(false)
            ->modalWidth(Width::Medium)
            ->hidden(fn (Ticket $record): bool => $record->isNotInCurrentPanel() || $record->isClosed)
            ->schema([
                Select::make('assignee_id')
                    ->label(__($key.'new_assignee'))
                    ->options(fn (Ticket $record): array => static::choices($record))
                    ->visible(fn (Ticket $record): bool => in_array(static::situation($record), [self::ASSIGN, self::REASSIGN], true))
                    ->searchable()
                    ->required()
                    ->hintAction(
                        Action::make('assign-to-me')
                            ->label(__($key.'assign_to_me'))
                            ->visible(fn (Ticket $record): bool => static::currentUserChoiceId($record) !== null)
                            ->action(fn (Set $set, Ticket $record) => $set('assignee_id', static::currentUserChoiceId($record))),
                    ),
            ])
            ->action(function (Ticket $record, array $data, Component $livewire): void {
                $assigneeId = static::situation($record) === self::ONLY_VIEWER ? static::currentUserChoiceId($record) : ($data['assignee_id'] ?? null);

                if (! resolve(TicketReassignment::class)->assign($record, $assigneeId)) {
                    $this->failureNotificationTitle(__('padmission-tickets::tickets.resources.tickets.invalid_assignee'));
                    $this->failure();

                    return;
                }

                $livewire->dispatch('refresh-sidebar');

                $this->success();
            })
            ->successNotificationTitle(__($key.'success'));
    }

    /*
     * The dialog asks only what there is to decide: nothing when nobody can be
     * picked, and a single "Assign to me" when the viewer is the only one.
     */
    protected static function situation(Ticket $record): string
    {
        $choices = static::choices($record);

        return match (true) {
            $choices === [] => self::NOBODY,
            count($choices) === 1 && static::currentUserChoiceId($record) !== null => self::ONLY_VIEWER,
            filled($record->assignee_id) => self::REASSIGN,
            default => self::ASSIGN,
        };
    }

    protected static function describe(Ticket $record): string
    {
        $key = 'padmission-tickets::tickets.actions.reassign.';

        if (static::situation($record) === self::NOBODY) {
            return TicketPlugin::get()->getAssignableUsersDescription() ?? __($key.'who_can_be_assigned');
        }

        $sentences = [
            $record->assignee !== null
                ? __($key.'modal_description', ['assignee' => Filament::getUserName($record->assignee)])
                : __($key.'modal_description_unassigned', ['requester' => $record->requesterName() ?? __($key.'the_requester')]),
            static::describeEscalationOwner($record),
        ];

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

        if ($escalation->isSubmittedBy(Filament::auth()->id())) {
            return TicketPlugin::teamText("{$key}_you", $team);
        }

        return $escalation->submitter === null
            ? null
            : TicketPlugin::teamText($key, $team, ['name' => resolve(GetUserDisplayName::class)->forUser($escalation->submitter)]);
    }

    /**
     * @return array<int|string, string>
     */
    protected static function choices(Ticket $record): array
    {
        return resolve(TicketReassignment::class)->choices($record);
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
