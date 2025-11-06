<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Http\DataMappers\TicketActivityMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\TicketPlugin;
use Tiptap\Editor;

class CreateMessageController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function __invoke(Request $request, int $ticket): array
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $this->authorize('create', $ticketModel);

        $validated = $request->validate([
            'content' => ['string', 'nullable', Rule::requiredIf(fn () => blank($request->array('attachment_ids')))],
            'attachment_ids' => ['array', Rule::requiredIf(fn () => blank($request->get('content')))],
            'lock_turn' => ['boolean'],
        ]);

        $this->validateAttachments($validated['attachment_ids']);

        // Remove global scopes to find the ticket and get its panel
        $ticketRecord = $ticketModel::withoutGlobalScopes()->findOrFail($ticket);

        // Get the plugin for this ticket's panel and verify against custom query
        $panelPlugin = TicketPlugin::get($ticketRecord->panel);
        $ticket = $panelPlugin->getTicketQuery()->findOrFail($ticket);

        $messages = collect();

        $content = $validated['content'] !== null
            ? (new Editor)->sanitize($validated['content'])
            : null;

        $isFirstActivity = ! $ticket->ticketActivities()->exists();

        if ($isFirstActivity) {
            $this->createFirstMessage($ticket);
        }

        DB::beginTransaction();

        $activity = $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => $request->user()->id === $ticket->submitter_id
                ? ActivitySender::User
                : ActivitySender::Supporter,
            'content' => $content,
        ]);

        $this->attachAttachments($activity, $validated['attachment_ids']);

        $activity->side = ActivitySide::Me;

        $messages->push($activity);

        $this->handleTurnChange($ticket, $activity, $validated['lock_turn']);

        if ($isFirstActivity) {
            $messages->push($this->createAutoResponse($ticket));
        }

        DB::commit();

        return [
            'messages' => $messages->map(fn ($message) => TicketActivityMapper::map($message)),
        ];
    }

    protected function validateAttachments(array $attachmentIds): void
    {
        $attachmentClass = TicketPlugin::resolveModelClass(TicketAttachment::class);

        $attachments = $attachmentClass::query()
            ->whereIn('id', $attachmentIds)
            ->get();

        foreach ($attachments as $attachment) {
            $actualSize = Storage::disk(config('padmission-tickets.attachments.disk'))->size($attachment->filepath);

            if ($attachment->file_size === $actualSize) {
                continue;
            }

            $attachments->each->delete();

            throw ValidationException::withMessages([
                'attachment_id' => sprintf('File size of %d does not match the expected size %d for attachment %d.', $attachment->file_size, $actualSize, $attachment->id),
            ]);
        }
    }

    protected function attachAttachments(TicketActivity $activity, array $attachmentIds): void
    {
        $attachmentClass = TicketPlugin::resolveModelClass(TicketAttachment::class);

        $attachmentClass::query()
            ->whereIn('id', $attachmentIds)
            ->update(['activity_id' => $activity->id]);
    }

    protected function handleTurnChange(Ticket $ticket, TicketActivity $activity, bool $lockTurn = false): void
    {
        $currentTurn = $ticket->turn;

        $nextTurn = match (true) {
            $lockTurn => $currentTurn,
            $activity->sender === ActivitySender::Supporter => Turn::User,
            $activity->sender === ActivitySender::User => Turn::Supporter,
            default => $currentTurn,
        };

        if ($currentTurn !== $nextTurn) {
            $ticket->ticketActivities()->create([
                'type' => ActivityType::TurnChanged,
                'sender' => ActivitySender::System,
                'data' => [
                    'from' => $currentTurn,
                    'to' => $nextTurn,
                ],
            ]);

            $ticket->update([
                'turn' => $nextTurn,
            ]);
        }
    }

    protected function createFirstMessage(?Ticket $ticket = null)
    {
        $config = TicketPlugin::get()->getChatWidgetConfig();

        return $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => ActivitySender::System,
            'content' => $config->getIntroMessage(),
        ]);
    }

    protected function createAutoResponse(?Ticket $ticket = null)
    {
        // TODO: Make this independent from Filament
        $config = TicketPlugin::get()->getChatWidgetConfig();

        $activity = $ticket->ticketActivities()->create([
            'type' => ActivityType::Message,
            'sender' => ActivitySender::System,
            'content' => $config->getAutoResponse(),
        ]);

        $activity->side = ActivitySide::System;

        return $activity;
    }
}
