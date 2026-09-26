<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TableSelect;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Padmission\Tickets\Filament\Tables\OpenEscalationsTable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

class AddToEscalationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'add-to-escalation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.add_to_escalation.label'))
            ->modalHeading(__('padmission-tickets::tickets.actions.add_to_escalation.label'))
            ->modalDescription(fn (): string => TicketPlugin::teamText(
                'padmission-tickets::tickets.actions.add_to_escalation.modal_description',
                TicketPlugin::get()->getEscalationTargetName(),
            ))
            ->modalSubmitActionLabel(__('padmission-tickets::tickets.actions.add_to_escalation.submit'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (Ticket $record): bool => CreateLinkedTicketAction::isAvailableFor($record))
            ->schema([
                TableSelect::make('escalation')
                    ->hiddenLabel()
                    ->relationshipName('parentTicket')
                    ->tableConfiguration(OpenEscalationsTable::class)
                    ->required(),
            ])
            ->action(function (Ticket $record, array $data): void {
                $refusal = resolve(TicketEscalationLinks::class)->addToEscalation($record, $data['escalation']);

                if ($refusal !== null) {
                    Notification::make()
                        ->danger()
                        ->title(__('padmission-tickets::tickets.resources.tickets.link_refused.title'))
                        ->body(__("padmission-tickets::tickets.resources.tickets.link_refused.{$refusal}", ['id' => $record->linked_ticket_id]))
                        ->send();

                    $this->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('padmission-tickets::tickets.actions.add_to_escalation.success', ['id' => $data['escalation']]))
                    ->send();
            });
    }
}
