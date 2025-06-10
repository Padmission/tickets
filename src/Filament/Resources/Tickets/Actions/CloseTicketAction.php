<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
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

        $this->action(function (Ticket $ticket, $data) {
            $ticket->close(
                disposition: $data['disposition'] ?? null,
                closedBy: Filament::auth()->id()
            );
        });
    }

    protected function dispositionsExist(): bool
    {
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);

        $cacheKey = $dispositionModel::getPanelCacheKey();

        return Cache::rememberForever($cacheKey, function () {
            return TicketDisposition::exists();
        });
    }
}
