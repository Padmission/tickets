<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Padmission\Tickets\Filament\Forms\Components\TicketSubjectInput;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\ScopesLookupsToTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketReassignment;

class EditTicketAction extends EditAction
{
    use ScopesLookupsToTicket;

    public static function getDefaultName(): ?string
    {
        return 'edit-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->closeModalByClickingAway(false)
            ->hidden(function (Ticket $record): bool {
                if ($record->isNotInCurrentPanel()) {
                    return true;
                }

                return $record->isClosed;
            })
            ->using(function (Ticket $record, array $data): Ticket {
                $assigneeId = Arr::pull($data, 'assignee_id');
                $reassigns = filled($assigneeId) && (string) $assigneeId !== (string) $record->assignee_id;
                $reassignment = resolve(TicketReassignment::class);

                if ($reassigns && ! $reassignment->isEligible($record, $assigneeId)) {
                    Notification::make()->danger()->title(__('padmission-tickets::tickets.resources.tickets.invalid_assignee'))->send();

                    $this->halt();
                }

                $record->update($data);

                if ($reassigns) {
                    $reassignment->assign($record, $assigneeId);
                }

                return $record;
            })
            ->after(function (Component $livewire) {
                $livewire->dispatch('refresh-sidebar');
            })
            ->schema([
                TicketSubjectInput::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->required()
                    ->maxLength(255),

                Select::make('assignee_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->options(fn (Ticket $record): array => static::assigneeOptions($record))
                    ->searchable()
                    // Nobody is unassigned here, as Reassign never does.
                    ->required(fn (Ticket $record): bool => filled($record->assignee_id)),

                Select::make('status_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.status'))
                    ->allowHtml()
                    ->native(false)
                    ->relationship('status', 'display_name', fn ($query) => $this->scopeLookupToTicket($query, $this->getRecord()))
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return Blade::render(<<<'HTML'
                            <div class="flex justify-start">
                                <x-filament::badge
                                    :color="$status->colorPalette"
                                    size="sm"
                                >
                                    {{ $status->display_name }}
                                </x-filament::badge>
                            </div>
                        HTML, [
                            'status' => $record,
                        ]);
                    })
                    ->required(),

                Select::make('priority_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.priority'))
                    ->allowHtml()
                    ->native(false)
                    ->relationship('priority', 'display_name', fn ($query) => $this->scopeLookupToTicket($query, $this->getRecord()))
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return Blade::render(<<<'HTML'
                            <div class="flex justify-start">
                                <x-filament::badge :color="$priority->colorPalette" size="sm">
                                    {{ $priority->display_name }}
                                </x-filament::badge>
                            </div>
                        HTML, [
                            'priority' => $record,
                        ]);
                    })
                    ->required(),
            ]);
    }

    /**
     * The current assignee stays listed, so the field shows them even once
     * they can no longer be picked.
     *
     * @return array<int|string, string>
     */
    protected static function assigneeOptions(Ticket $record): array
    {
        $options = resolve(TicketReassignment::class)->eligible($record);

        if (filled($record->assignee_id) && ! array_key_exists($record->assignee_id, $options) && $record->assignee !== null) {
            $options = [$record->assignee_id => Filament::getUserName($record->assignee), ...$options];
        }

        return $options;
    }
}
