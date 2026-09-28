<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TableSelect;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns\TellsRequester;
use Padmission\Tickets\Filament\Tables\OpenEscalationsTable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;

class AddToEscalationAction extends Action
{
    use TellsRequester;

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
            ->visible(fn (Ticket $record): bool => static::isAvailableFor($record))
            ->fillForm(fn (Ticket $record): array => static::requesterMessageDefaults($record))
            ->schema([
                TableSelect::make('escalation')
                    ->hiddenLabel()
                    ->relationshipName('parentTicket')
                    ->tableConfiguration(OpenEscalationsTable::class)
                    ->required(),

                ...static::requesterMessageFields(),
            ])
            ->action(function (Ticket $record, array $data): void {
                $refusal = DB::transaction(function () use ($record, $data): ?string {
                    $refusal = resolve(TicketEscalationLinks::class)->addToEscalation($record, $data['escalation']);

                    if ($refusal === null) {
                        static::tellRequester($record, $data);
                    }

                    return $refusal;
                });

                if ($refusal !== null) {
                    Notification::make()
                        ->danger()
                        ->title(__('padmission-tickets::tickets.resources.tickets.link_refused.title'))
                        ->body(__("padmission-tickets::tickets.resources.tickets.link_refused.{$refusal}"))
                        ->send();

                    $this->halt();
                }

                Notification::make()
                    ->success()
                    ->title(TicketPlugin::teamText('padmission-tickets::tickets.actions.add_to_escalation.success', TicketPlugin::get()->getEscalationTargetName()))
                    ->send();
            });
    }

    /*
     * An original that could be escalated but whose organization has no open
     * escalation to join would only open an empty picker.
     */
    public static function isAvailableFor(Ticket $record): bool
    {
        return CreateLinkedTicketAction::isAvailableFor($record)
            && OpenEscalationsTable::hasEscalationsFor($record);
    }
}
