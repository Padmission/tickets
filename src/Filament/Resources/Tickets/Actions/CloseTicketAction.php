<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Component;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
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

        $this->schema(fn (Ticket $record): array => $this->dispositionsExist($record) ? [
            Select::make('disposition')
                ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                ->relationship(
                    'disposition',
                    'display_name',
                    fn ($query) => $this->scopeDispositionsToTicket($query, $this->getRecord()),
                )
                ->lazy()
                ->required(),
        ] : []);

        $this->action(function (Ticket $record, Component $livewire, $data) {
            $record->close(
                dispositionId: $data['disposition'] ?? null,
                closedById: Filament::auth()->id()
            );

            $livewire->dispatch('refresh-sidebar');
        });
    }

    /*
     * Asked the way the disposition options are loaded, so a disposition is
     * required only when the ticket's own panel and tenant offers one.
     */
    protected function dispositionsExist(Ticket $record): bool
    {
        $query = Relation::noConstraints(fn (): Relation => $record->disposition())->getQuery();

        return $this->scopeDispositionsToTicket($query, $record)->exists();
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
