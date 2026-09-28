<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;
use Padmission\Tickets\Models\Ticket;

/*
 * Staff reopen a ticket of their own panel without writing to it; anyone who
 * replies to a closed ticket is asked instead.
 */
class ReopenTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reopen-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $key = 'padmission-tickets::tickets.actions.reopen.';

        $this
            ->label(__($key.'label'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalHeading(__($key.'modal_heading'))
            ->modalDescription(fn (Ticket $record): string => __($record->isEscalation() ? $key.'modal_description_escalation' : $key.'modal_description'))
            ->modalSubmitActionLabel(__($key.'submit'))
            ->visible(fn (Ticket $record): bool => $record->isClosed && $record->panel === Filament::getCurrentOrDefaultPanel()->getId())
            ->authorize('reopen')
            ->successNotificationTitle(__($key.'success'))
            ->action(function (Ticket $record, Component $livewire): void {
                $record->reopen(Filament::auth()->id());

                $this->success();
                $livewire->dispatch('refresh-sidebar');
            });
    }
}
