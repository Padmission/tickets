<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class CloseTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'close-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.close.label'))
            ->modalHeading(__('padmission-tickets::tickets.actions.close.modal_heading'))
            ->button()
            ->color('gray')
            ->hidden(fn (Ticket $record): bool => $record->isClosed)
            ->requiresConfirmation()
            ->icon('heroicon-o-check-circle');

        $plugin = TicketPlugin::get();
        $dispositionEnum = $plugin->getDispositionEnum();

        if ($dispositionEnum !== null) {
            $this->form([
                Select::make('disposition')
                    ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                    ->options($dispositionEnum)
                    ->required(),
            ]);
        }

        $this->action(function (Ticket $ticket, $data) {
            $ticket->close(
                disposition: $data['disposition'] ?? null,
                closedBy: Filament::auth()->id()
            );
        });
    }
}
