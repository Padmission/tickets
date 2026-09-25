<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketActivityService;

class ViewOriginalConversationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'view-original-conversation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.view_original_conversation.label'))
            ->modalHeading(__('padmission-tickets::tickets.actions.view_original_conversation.modal_heading'))
            ->modalDescription(__('padmission-tickets::tickets.actions.view_original_conversation.modal_description'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('padmission-tickets::tickets.actions.view_original_conversation.close'))
            ->visible(fn (Ticket $record): bool => $record->isInCurrentPanel() && static::originalTickets($record)->isNotEmpty())
            ->modalContent(fn (Ticket $record) => view('padmission-tickets::filament.original-conversation', [
                'escalatedTicket' => $record,
                'originalTickets' => static::originalTickets($record),
                'activityService' => resolve(TicketActivityService::class),
            ]));
    }

    /**
     * @return Collection<int, Ticket>
     */
    protected static function originalTickets(Ticket $record): Collection
    {
        $user = Filament::auth()->user();

        return $record->childTickets
            ->filter(fn (Ticket $ticket): bool => $user !== null && Gate::forUser($user)->allows('view', $ticket))
            ->values();
    }
}
