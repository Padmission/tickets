<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

/*
 * The person handling an escalation closes it from their side when the other
 * team's part is done, the way a requester resolves their own ticket: no
 * disposition, since that is the other team's to record.
 */
class CloseEscalationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'close-escalation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $key = 'padmission-tickets::tickets.actions.close_escalation.';

        $this
            ->label(__($key.'label'))
            ->modalHeading(__($key.'modal_heading'))
            ->modalDescription(fn (Ticket $record): string => TicketPlugin::teamText(
                $key.'modal_description',
                TicketPlugin::find($record->panel)?->getSupportTeamName(),
            ))
            ->modalSubmitActionLabel(__($key.'submit'))
            ->button()
            ->color('gray')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->slideOver(false)
            ->authorize(fn (Ticket $record): bool => static::isAvailableFor($record))
            ->action(function (Ticket $record, Component $livewire): void {
                if (! static::isAvailableFor($record)) {
                    $this->failure();

                    return;
                }

                $record->close(closedById: Filament::auth()->id());

                $this->success();

                // The chat keeps its composer until the page loads again.
                if ($livewire instanceof ViewTicket) {
                    $this->redirect(TicketResource::getUrl('view', array_filter([
                        'record' => $record,
                        'linked' => $livewire->linkedTicketId,
                    ])));

                    return;
                }

                $livewire->dispatch('refresh-sidebar');
            })
            ->successNotificationTitle(__($key.'success'));
    }

    public static function isAvailableFor(Ticket $record): bool
    {
        return $record->isOpen
            && Filament::auth()->id() !== null
            && (string) Filament::auth()->id() === (string) $record->submitter_id
            && $record->isEscalationFrom(Filament::getCurrentOrDefaultPanel()->getId());
    }
}
