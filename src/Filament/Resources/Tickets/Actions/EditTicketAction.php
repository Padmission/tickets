<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class EditTicketAction extends EditAction
{
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
            ->after(function (Component $livewire) {
                $livewire->dispatch('refresh-sidebar');
            })
            ->schema([
                TextInput::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->disabled()
                    ->required(),

                Select::make('status_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.status'))
                    ->allowHtml()
                    ->native(false)
                    ->relationship('status', 'display_name', fn ($query) => $query->tap(new CurrentPanelScope))
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
                    ->relationship('priority', 'display_name', fn ($query) => $query->tap(new CurrentPanelScope))
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

                Select::make('assignee_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->relationship('assignee', 'name', function ($query) {
                        $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

                        if ($allSupportersQuery) {
                            $supporterIds = app()->call($allSupportersQuery)->pluck('id');

                            return $query->whereIn('id', $supporterIds);
                        }

                        return $query;
                    })
                    ->preload()
                    ->searchable()
                    ->required(),
            ]);
    }
}
