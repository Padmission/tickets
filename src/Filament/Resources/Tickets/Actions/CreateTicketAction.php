<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Padmission\Tickets\Actions\GetDefaultPriorityForPanel;
use Padmission\Tickets\Actions\GetDefaultStatusForPanel;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class CreateTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'open-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.open_ticket.label'))
            ->button()
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->closeModalByClickingAway(false)
            ->schema([
                TextInput::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('message')
                    ->label(__('padmission-tickets::tickets.actions.open_ticket.message'))
                    ->rows(5)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $panelId = Filament::getCurrentOrDefaultPanel()->getId();
                $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
                $ticketAttributes = [
                    'panel' => $panelId,
                    'source_panel' => $panelId,
                    'subject' => $data['subject'],
                    'status_id' => app(GetDefaultStatusForPanel::class)($panelId)->getKey(),
                    'priority_id' => app(GetDefaultPriorityForPanel::class)($panelId)->getKey(),
                    'submitter_id' => Filament::auth()->id(),
                    'turn' => Turn::Supporter,
                    'data' => [],
                ];

                if (config('padmission-tickets.tenancy.enabled', false) && Filament::auth()->user()) {
                    $tenantKey = Str::snake(class_basename(config('padmission-tickets.tenancy.tenancy_model'))).'_id';
                    $ticketAttributes[$tenantKey] = Filament::auth()->user()->getAttribute($tenantKey);
                }

                /** @var Ticket $ticket */
                $ticket = $ticketModel::create($ticketAttributes);

                $ticket->addTicketActivity(
                    type: ActivityType::Message,
                    sender: ActivitySender::User,
                    userId: Filament::auth()->id(),
                    content: $data['message'],
                );

                Notification::make()
                    ->success()
                    ->title(__('padmission-tickets::tickets.actions.open_ticket.notifications.success'))
                    ->send();

                $this->successRedirectUrl(TicketResource::getUrl('view', ['record' => $ticket]));
            });
    }
}
