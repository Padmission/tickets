<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Actions\GetDefaultPriorityForPanel;
use Padmission\Tickets\Actions\GetDefaultStatusForPanel;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

use function count;

class CreateLinkedTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'create-linked-ticket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.label'))
            ->icon(Heroicon::Link)
            ->color('gray')
            ->visible(function (Ticket $record) {
                if ($record->panel !== Filament::getCurrentOrDefaultPanel()->getId()) {
                    return false;
                }

                return count(TicketPlugin::get()->getLinkedTicketParentPanels()) > 0
                    && $record->parentTicket === null;
            })
            ->slideOver()
            ->modalWidth(Width::Large)
            ->closeModalByClickingAway(false)
            ->fillForm(fn (Ticket $record) => ['subject' => $record->subject])
            ->schema([
                Select::make('panel')
                    ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.form.panel'))
                    ->required()
                    ->visible(fn () => count(TicketPlugin::get()->getLinkedTicketParentPanels()) > 1)
                    ->options(
                        collect(TicketPlugin::get()->getLinkedTicketParentPanels())
                            ->mapWithKeys(fn (Panel $panel) => [$panel->getId() => ucfirst($panel->getId())])
                    ),

                TextInput::make('subject')
                    ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.form.subject'))
                    ->required(),

                RichEditor::make('message')
                    ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.form.message'))
                    ->required()
                    ->toolbarButtons(['bold', 'link', 'bulletList', 'orderedList']),
            ])
            ->action(function (array $data, ViewTicket $livewire) {
                $ticket = TicketPlugin::resolveModelClass(Ticket::class);
                $currentPanelId = Filament::getCurrentOrDefaultPanel()->getId();
                $targetPanelId = $data['panel'] ?? array_keys(TicketPlugin::get()->getLinkedTicketParentPanels())[0];

                $defaultStatus = resolve(GetDefaultStatusForPanel::class)($targetPanelId);
                $defaultPriority = resolve(GetDefaultPriorityForPanel::class)($targetPanelId);

                DB::beginTransaction();

                $newTicket = $ticket::create([
                    'panel' => $targetPanelId,
                    'source_panel' => $currentPanelId,
                    'subject' => $data['subject'],
                    'submitter_id' => Filament::auth()->id(),
                    'turn' => Turn::Supporter,
                    'status_id' => $defaultStatus->id,
                    'priority_id' => $defaultPriority->id,
                ]);

                $newTicket->ticketActivities()->create([
                    'sender' => ActivitySender::User,
                    'type' => ActivityType::Message,
                    'content' => $data['message'],
                ]);

                /**
                 * @var Ticket $record
                 */
                $record = $livewire->record;

                $record->parentTicket()->associate($newTicket);
                $record->save();

                DB::commit();

                $livewire->data['parentTicket'] = $newTicket->id;

                Notification::make()
                    ->success()
                    ->title(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.success.title'))
                    ->body(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.success.body'))
                    ->actions([
                        Action::make('link')
                            ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.success.action_label'))
                            ->url(
                                TicketResource::getUrl(
                                    'view',
                                    ['record' => $newTicket],
                                    panel: $currentPanelId
                                )
                            ),
                    ])
                    ->send();
            });
    }
}
