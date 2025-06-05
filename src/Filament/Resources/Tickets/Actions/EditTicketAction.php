<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Models\Ticket;

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
            ->modalWidth(MaxWidth::Medium)
            ->closeModalByClickingAway(false)
            ->hidden(fn (Ticket $record): bool => $record->isClosed)
            ->form([
                TextInput::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->disabled()
                    ->required(),

                Select::make('status_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.status'))
                    ->allowHtml()
                    ->native(false)
                    ->relationship('status', 'display_name', fn ($query) => $query->tap(new CurrentPanelScope))
                    ->getOptionLabelFromRecordUsing(function (TicketStatus $status) {
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
                            'status' => $status,
                        ]);
                    })
                    ->required(),

                Select::make('priority_id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.priority'))
                    ->allowHtml()
                    ->native(false)
                    ->relationship('priority', 'display_name', fn ($query) => $query->tap(new CurrentPanelScope))
                    ->getOptionLabelFromRecordUsing(function (TicketPriority $priority) {
                        return Blade::render(<<<'HTML'
                            <div class="flex justify-start">
                                <x-filament::badge :color="$priority->colorPalette" size="sm">
                                    {{ $priority->display_name }}
                                </x-filament::badge>
                            </div>
                        HTML, [
                            'priority' => $priority,
                        ]);
                    })
                    ->required(),

                Select::make('assignee_id')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->required(),
            ]);
    }
}
