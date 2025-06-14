<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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
            ->icon('heroicon-o-check-circle');

        $hasDispositions = $this->dispositionsExist();

        if ($hasDispositions) {
            $this->form([
                Select::make('disposition')
                    ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                    ->relationship('disposition', 'display_name')
                    ->lazy()
                    ->required(),
            ]);
        }

        $this->action(function (Ticket $record, $data) {
            $record->close(
                disposition: $data['disposition'] ?? null,
                closedBy: Filament::auth()->id()
            );
        });
    }

    protected function dispositionsExist(): bool
    {
        $dispositionModel = \Padmission\Tickets\TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\TicketDisposition::class);

        return $dispositionModel::query()->exists();
    }
}
