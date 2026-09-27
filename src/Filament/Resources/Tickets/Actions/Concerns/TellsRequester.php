<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Actions\Concerns;

use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketAuth;
use Tiptap\Editor;

/*
 * The requester never hears about an escalation, so escalating offers to send
 * them an ordinary reply instead: the only sign they get that someone is on it.
 */
trait TellsRequester
{
    /**
     * @return array<string, mixed>
     */
    protected static function requesterMessageDefaults(Ticket $record): array
    {
        return [
            'notify_requester' => $record->turn === Turn::Supporter,
            'requester_message' => __('padmission-tickets::tickets.actions.create_linked_ticket.form.requester_message_default'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function requesterMessageFields(): array
    {
        $key = 'padmission-tickets::tickets.actions.create_linked_ticket.form.';

        return [
            Toggle::make('notify_requester')
                ->label(fn (Ticket $record): string => __($key.'notify_requester', ['name' => $record->requesterName()]))
                ->visible(fn (Ticket $record): bool => static::canTellRequester($record))
                ->live(),

            RichEditor::make('requester_message')
                ->label(fn (Ticket $record): string => __($key.'requester_message', ['name' => $record->requesterName()]))
                ->helperText(fn (Ticket $record): string => __($key.'requester_message_helper', ['name' => $record->requesterName()]))
                ->visible(fn (Ticket $record, Get $get): bool => static::canTellRequester($record) && (bool) $get('notify_requester'))
                ->required()
                ->toolbarButtons(['bold', 'link', 'bulletList', 'orderedList']),
        ];
    }

    /*
     * Only a requester with an account gets a reply, and nobody tells
     * themselves anything.
     */
    protected static function canTellRequester(Ticket $record): bool
    {
        $viewer = Filament::auth()->user();

        return $record->submitter !== null
            && ! $record->isSubmittedBy($viewer)
            && resolve(TicketAuth::class)->canReply($record, $viewer);
    }

    /**
     * Call inside the transaction that escalates, so the reply exists only if
     * the escalation does. The turn stays with the organization: the answer
     * is still owed.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function tellRequester(Ticket $original, array $data): bool
    {
        $content = $data['requester_message'] ?? null;

        if (! ($data['notify_requester'] ?? false) || blank($content) || ! static::canTellRequester($original)) {
            return false;
        }

        $original->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => ActivitySender::Supporter,
            'user_id' => Filament::auth()->id(),
            'content' => (new Editor)->sanitize($content),
        ]);

        return true;
    }
}
