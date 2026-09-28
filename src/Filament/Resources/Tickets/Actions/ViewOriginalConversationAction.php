<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\TicketPlugin;

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
            ->fillForm(fn (Ticket $record): array => ['original' => static::originalTickets($record)->first()?->getKey()])
            ->schema([
                Select::make('original')
                    ->label(__('padmission-tickets::tickets.actions.view_original_conversation.choose'))
                    ->options(fn (Ticket $record): array => static::originalTickets($record)
                        ->mapWithKeys(fn (Ticket $ticket): array => [$ticket->getKey() => static::optionLabel($ticket)])
                        ->all())
                    ->visible(fn (Ticket $record): bool => static::originalTickets($record)->count() > 1)
                    ->selectablePlaceholder(false)
                    ->live(),

                View::make('padmission-tickets::filament.original-conversation')
                    ->viewData(fn (Ticket $record, Get $get): array => [
                        'escalatedTicket' => $record,
                        'originalTickets' => static::originalTickets($record)
                            ->filter(fn (Ticket $ticket): bool => $ticket->getKey() == $get('original'))
                            ->values(),
                        'activityService' => resolve(TicketActivityService::class),
                    ]),
            ]);
    }

    protected static function optionLabel(Ticket $ticket): string
    {
        return collect([
            "#{$ticket->getKey()} {$ticket->subject}",
            TicketPlugin::get()->describeTicketOrigin($ticket),
            $ticket->submitter ? Filament::getUserName($ticket->submitter) : null,
        ])->filter()->implode(' · ');
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
