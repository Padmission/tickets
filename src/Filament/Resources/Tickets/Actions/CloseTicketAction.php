<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\Models\TicketDisposition;
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
            $this->schema([
                Select::make('disposition')
                    ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                    ->relationship('disposition', 'display_name')
                    ->lazy()
                    ->required(),
            ]);
        }

        $this->action(function (Ticket $record, $data) {
            $record->close(
                dispositionId: $data['disposition'] ?? null,
                closedById: Filament::auth()->id()
            );
        });
    }

    protected function dispositionsExist(): bool
    {
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);

        return $dispositionModel::query()->exists();
    }
}
