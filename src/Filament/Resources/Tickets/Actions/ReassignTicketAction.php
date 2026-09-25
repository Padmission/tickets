<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;
use Padmission\Tickets\Models\Ticket;
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
            ->modalDescription(fn (): string => collect([
                __('padmission-tickets::tickets.actions.reassign.modal_description'),
                count(TicketPlugin::get()->getLinkedTicketParentPanels()) > 0
                    ? __('padmission-tickets::tickets.actions.reassign.modal_description_escalation')
                    : null,
            ])->filter()->implode(' '))
            ->modalSubmitActionLabel(__('padmission-tickets::tickets.actions.reassign.submit'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->modalWidth(Width::Medium)
            ->hidden(fn (Ticket $record): bool => $record->isNotInCurrentPanel() || $record->isClosed)
            ->fillForm(fn (Ticket $record): array => ['assignee_id' => $record->assignee_id])
            ->schema([
                Select::make('assignee_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->helperText(__('padmission-tickets::tickets.actions.reassign.assignee_helper'))
                    ->options(fn (): array => static::supporterOptions())
                    // The current assignee may no longer be a supporter, and
                    // should still show by name rather than as a raw key.
                    ->getOptionLabelUsing(fn (Ticket $record, $value): ?string => static::supporterOptions()[$value]
                        ?? ($record->assignee?->getKey() == $value ? $record->assignee->name : null))
                    ->searchable()
                    ->required()
                    ->hintAction(
                        Action::make('assign-to-me')
                            ->label(__('padmission-tickets::tickets.actions.reassign.assign_to_me'))
                            ->visible(fn (): bool => static::currentUserSupporterId() !== null)
                            ->action(fn (Set $set) => $set('assignee_id', static::currentUserSupporterId())),
                    ),
            ])
            ->action(function (Ticket $record, array $data, Component $livewire): void {
                if (! array_key_exists($data['assignee_id'], static::supporterOptions())) {
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

    /**
     * @return array<int|string, string>
     */
    protected static function supporterOptions(): array
    {
        $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

        if (! $allSupportersQuery) {
            return [];
        }

        return app()->call($allSupportersQuery)->pluck('name', 'id')->all();
    }

    protected static function currentUserSupporterId(): int|string|null
    {
        $supporterIds = array_keys(static::supporterOptions());

        foreach (TicketPlugin::get()->getCurrentUserAssigneeIds() as $id) {
            if (in_array($id, $supporterIds)) {
                return $id;
            }
        }

        return null;
    }
}
