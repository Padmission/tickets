<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
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
            ->modalDescription(__('padmission-tickets::tickets.actions.close.modal_description'))
            ->modalSubmitActionLabel(__('padmission-tickets::tickets.actions.close.submit'))
            ->button()
            ->color('gray')
            ->hidden(function ($record): bool {
                if ($record->panel !== Filament::getCurrentOrDefaultPanel()->getId()) {
                    return true;
                }

                return $record->isClosed;
            })
            ->requiresConfirmation()
            ->slideOver(false)
            ->icon('heroicon-o-check-circle');

        $hasDispositions = $this->dispositionsExist();

        if ($hasDispositions) {
            $this->schema([
                Select::make('disposition')
                    ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                    ->relationship(
                        'disposition',
                        'display_name',
                        fn ($query) => $this->scopeDispositionsToTicket($query, $this->getRecord()),
                    )
                    ->lazy()
                    ->required(),
            ]);
        }

        $this->action(function (Ticket $record, Component $livewire, $data) {
            $record->close(
                dispositionId: $data['disposition'] ?? null,
                closedById: Filament::auth()->id()
            );

            $livewire->dispatch('refresh-sidebar');
        });
    }

    protected function dispositionsExist(): bool
    {
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);

        return $dispositionModel::query()->exists();
    }

    protected function scopeDispositionsToTicket(Builder $query, mixed $ticket): Builder
    {
        $query->withoutGlobalScope(CurrentPanelScope::class);

        if ($ticket instanceof Ticket && filled($ticket->panel)) {
            $query->where($query->getModel()->qualifyColumn('panel'), $ticket->panel);
        }

        if (
            $ticket instanceof Ticket
            && config('padmission-tickets.tenancy.enabled')
            && filled($ticket->getAttribute('tenant_id'))
        ) {
            $query->where($query->getModel()->qualifyColumn('tenant_id'), $ticket->tenant_id);
        }

        return $query;
    }
}
