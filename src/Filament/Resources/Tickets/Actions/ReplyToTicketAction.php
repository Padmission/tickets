<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Width;
use Livewire\Component;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;

class ReplyToTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reply-to-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.reply.label'))
            ->button()
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->closeModalByClickingAway(false)
            ->hidden(function (Ticket $record): bool {
                return $record->isNotInCurrentPanel() || $record->isClosed;
            })
            ->schema([
                Textarea::make('message')
                    ->label(__('padmission-tickets::tickets.actions.reply.message'))
                    ->rows(5)
                    ->required(),
            ])
            ->action(function (Ticket $record, Component $livewire, array $data): void {
                $record->addTicketActivity(
                    type: ActivityType::Message,
                    sender: ActivitySender::Supporter,
                    userId: Filament::auth()->id(),
                    content: $data['message'],
                );

                $record->forceFill([
                    'turn' => Turn::User,
                    'updated_at' => now(),
                ])->save();

                $livewire->dispatch('message-sent');
            });
    }
}
