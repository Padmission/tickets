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
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

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
            ->label(fn (Ticket $record): string => static::translate(static::isRelinking($record) ? 'label_again' : 'label'))
            ->tooltip(fn (): string => static::translate('tooltip'))
            ->modalHeading(fn (Ticket $record): string => static::translate(static::isRelinking($record) ? 'modal_heading_again' : 'modal_heading'))
            ->modalDescription(fn (Ticket $record): string => static::translate(static::isRelinking($record) ? 'modal_description_again' : 'modal_description'))
            ->modalSubmitActionLabel(__('padmission-tickets::tickets.actions.create_linked_ticket.submit'))
            ->icon(Heroicon::ArrowUpTray)
            ->color('gray')
            ->visible(fn (Ticket $record): bool => static::isAvailableFor($record))
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
                            ->mapWithKeys(fn (Panel $panel) => [$panel->getId() => TicketPlugin::getSupportTeamNameForPanel($panel) ?? ucfirst($panel->getId())])
                    ),

                TextInput::make('subject')
                    ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.form.subject'))
                    ->required(),

                RichEditor::make('message')
                    ->label(__('padmission-tickets::tickets.actions.create_linked_ticket.form.message'))
                    ->helperText(__('padmission-tickets::tickets.actions.create_linked_ticket.form.message_helper'))
                    ->required()
                    ->toolbarButtons(['bold', 'link', 'bulletList', 'orderedList']),
            ])
            ->action(function (array $data, ViewTicket $livewire, Action $action) {
                $ticket = TicketPlugin::resolveModelClass(Ticket::class);
                $currentPanelId = Filament::getCurrentOrDefaultPanel()->getId();
                $targetPanelId = $data['panel'] ?? array_keys(TicketPlugin::get()->getLinkedTicketParentPanels())[0];

                try {
                    $defaultStatus = resolve(GetDefaultStatusForPanel::class)($targetPanelId);
                    $defaultPriority = resolve(GetDefaultPriorityForPanel::class)($targetPanelId);
                } catch (RuntimeException $exception) {
                    // The target panel was never given statuses or priorities for
                    // this tenant: a setup gap the user cannot fix from here.
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.not_configured.title'))
                        ->body(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.not_configured.body', [
                            'panel' => TicketPlugin::find($targetPanelId)?->getSupportTeamName() ?? ucfirst($targetPanelId),
                        ]))
                        ->send();

                    // halt() always throws, but is typed void.
                    $action->halt();

                    return;
                }

                /**
                 * @var Ticket $record
                 */
                $record = $livewire->record;

                $links = resolve(TicketEscalationLinks::class);

                // The page may have been loaded before someone else escalated this
                // ticket, and a host scope can hide that escalation, so the link is
                // re-checked under a lock and nothing is created if it is taken.
                $newTicket = DB::transaction(function () use ($ticket, $targetPanelId, $currentPanelId, $data, $defaultStatus, $defaultPriority, $record, $links) {
                    if (! $links->canOpenEscalation($record)) {
                        return null;
                    }

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

                    $links->linkNewEscalation($record, $newTicket);

                    return $newTicket;
                });

                if ($newTicket === null) {
                    Notification::make()
                        ->danger()
                        ->title(__('padmission-tickets::tickets.resources.tickets.link_refused.title'))
                        ->body(__('padmission-tickets::tickets.resources.tickets.link_refused.already_escalated'))
                        ->send();

                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('padmission-tickets::tickets.actions.create_linked_ticket.notifications.success.title'))
                    ->body(static::translate('notifications.success.body'))
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

    /*
     * A link to an escalation that was closed or deleted does not block a new
     * one; escalating or adding replaces it.
     */
    public static function isAvailableFor(Ticket $record): bool
    {
        return $record->isInCurrentPanel()
            && $record->isOpen
            && count(TicketPlugin::get()->getLinkedTicketParentPanels()) > 0
            && (blank($record->linked_ticket_id) || ! resolve(TicketEscalationLinks::class)->hasOpenEscalation($record));
    }

    /*
     * Only a closed escalation stays in the ticket's history to mention; a
     * deleted or missing one is escalated as if for the first time.
     */
    protected static function isRelinking(Ticket $record): bool
    {
        return resolve(TicketEscalationLinks::class)->hasClosedEscalation($record);
    }

    protected static function translate(string $key): string
    {
        return TicketPlugin::teamText(
            "padmission-tickets::tickets.actions.create_linked_ticket.{$key}",
            TicketPlugin::get()->getEscalationTargetName(),
        );
    }
}
