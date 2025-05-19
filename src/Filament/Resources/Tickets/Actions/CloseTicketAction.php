<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Padmission\Tickets\Models\Ticket;

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
            ->icon('heroicon-o-check-circle')
            ->action(function (Ticket $ticket) {
                $ticket->close(closedBy: Filament::auth()->id());
            });
    }
}
