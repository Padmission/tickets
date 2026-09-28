<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\DeleteAction;
use Padmission\Tickets\Models\Ticket;

class DeleteTicketAction extends DeleteAction
{
    public static function getDefaultName(): ?string
    {
        return 'delete-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $key = 'padmission-tickets::tickets.actions.delete.';

        $this
            ->label(__($key.'label'))
            ->modalHeading(__($key.'modal_heading'))
            ->modalDescription(fn (Ticket $record): string => __($record->isEscalation() ? $key.'modal_description_escalation' : $key.'modal_description'))
            ->modalSubmitActionLabel(__($key.'submit'))
            ->successNotificationTitle(__($key.'success'));
    }
}
