<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;

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
            ->hidden(fn ($record): bool => $record->isClosed)
            ->requiresConfirmation()
            ->icon('heroicon-o-check-circle')
            ->action(function ($record) {
                $record->close(closedBy: Filament::auth()->id());
            });
    }
}
