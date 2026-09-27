<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Component;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\ScopesLookupsToTicket;
use Padmission\Tickets\Models\Ticket;

class CloseTicketAction extends Action
{
    use ScopesLookupsToTicket;

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
                    fn ($query) => $this->scopeLookupToTicket($query, $this->getRecord()),
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

        return $this->scopeLookupToTicket($query, $record)->exists();
    }
}
