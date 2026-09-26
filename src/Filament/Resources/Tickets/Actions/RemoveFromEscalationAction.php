<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

class RemoveFromEscalationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'remove-from-escalation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.remove_from_escalation.label'))
            ->modalHeading(__('padmission-tickets::tickets.actions.remove_from_escalation.label'))
            ->modalDescription(__('padmission-tickets::tickets.actions.remove_from_escalation.modal_description'))
            ->modalSubmitActionLabel(__('padmission-tickets::tickets.actions.remove_from_escalation.submit'))
            ->requiresConfirmation()
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('gray')
            ->link()
            ->visible(fn (Ticket $record): bool => $record->isInCurrentPanel() && filled($record->linked_ticket_id))
            ->action(function (Ticket $record): void {
                if (! resolve(TicketEscalationLinks::class)->removeFromEscalation($record)) {
                    return;
                }

                Notification::make()
                    ->success()
                    ->title(TicketPlugin::teamText('padmission-tickets::tickets.actions.remove_from_escalation.success', TicketPlugin::get()->getEscalationTargetName()))
                    ->send();
            });
    }
}
