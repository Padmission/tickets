<?php

namespace Padmission\Tickets\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
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
use Padmission\Tickets\Services\ApiTicketResolver;
use Padmission\Tickets\Services\TicketAuth;
use Padmission\Tickets\Services\TicketReopening;
use Padmission\Tickets\Support\MessageHtml;
use Padmission\Tickets\TicketPlugin;

class CreateMessageController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    // What the content column, a MySQL TEXT, holds.
    protected const MAX_STORED_BYTES = 65535;

    public function __invoke(Request $request, int $ticket): array
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        $validated = $request->validate([
            'content' => ['string', 'nullable', 'max:'.(int) config('padmission-tickets.api.max_message_length', 16000), Rule::requiredIf(fn () => blank($request->array('attachment_ids')))],
            'attachment_ids' => ['array', Rule::requiredIf(fn () => blank($request->get('content')))],
            'lock_turn' => ['boolean'],
            'reopen' => ['boolean'],
        ]);

        $ticket = resolve(ApiTicketResolver::class)->resolve($ticket, $request->user());

        // `create` is the chat widget's audience, so it holds the requester, never support replying.
        if ($ticket->isSubmittedBy($request->user())) {
            $this->authorize('create', $ticketModel);
        }

        resolve(TicketAuth::class)->authorizeReply($ticket, $request->user());
        resolve(TicketAuth::class)->refuseDisabledReply($ticket, $request->user());

        // Reopened only on the writer's say-so, and only by those who may.
        if ($ticket->isClosed && $request->boolean('reopen')
            && in_array(TicketReopening::REOPEN, resolve(TicketReopening::class)->choicesFor($ticket, $request->user()), true)) {
            $ticket->reopen($request->user()->getAuthIdentifier());
        }

        resolve(TicketAuth::class)->refuseClosedTicket($ticket);

        $attachmentIds = $validated['attachment_ids'] ?? [];

        $this->validateAttachments($ticket, $request->user(), $attachmentIds);

        $messages = collect();

        $content = ($validated['content'] ?? null) !== null
            ? MessageHtml::sanitize($validated['content'])
            : null;

        // Cleaning writes characters out as entities, so the stored HTML can run longer than what was sent.
        if ($content !== null && strlen($content) > self::MAX_STORED_BYTES) {
            throw ValidationException::withMessages(['content' => __('validation.max.string', [
                'attribute' => 'content',
                'max' => (int) config('padmission-tickets.api.max_message_length', 16000),
            ])]);
        }

        if ($content === '' && blank($attachmentIds)) {
            throw ValidationException::withMessages(['content' => __('validation.required', ['attribute' => 'content'])]);
        }

        // A new ticket's link back to the one it follows up is not its opening.
        $isFirstActivity = ! $ticket->ticketActivities()->whereNot('type', ActivityType::FollowsUp)->exists();

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

        $this->attachAttachments($activity, $ticket, $request->user(), $attachmentIds);

        $activity->side = ActivitySide::Me;
        $activity->isOwn = true;

        $messages->push($activity);

        // Only the answering side may keep a conversation waiting on itself.
        $lockTurn = $activity->sender === ActivitySender::Supporter && ($validated['lock_turn'] ?? false);

        $this->handleTurnChange($ticket, $activity, $lockTurn);

        if ($isFirstActivity) {
            $messages->push($this->createAutoResponse($ticket));
        }

        DB::commit();

        return [
            'messages' => $messages->map(fn ($message) => TicketActivityMapper::map($message)),
        ];
    }

    protected function validateAttachments(Ticket $ticket, ?Authenticatable $user, array $attachmentIds): void
    {
        if (blank($attachmentIds)) {
            return;
        }

        $attachments = $this->pendingAttachmentsQuery($ticket, $user, $attachmentIds)->get();

        if ($attachments->count() !== count(array_unique($attachmentIds))) {
            throw ValidationException::withMessages([
                'attachment_ids' => 'One or more attachments are invalid for this ticket.',
            ]);
        }

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

    protected function attachAttachments(TicketActivity $activity, Ticket $ticket, ?Authenticatable $user, array $attachmentIds): void
    {
        if (blank($attachmentIds)) {
            return;
        }

        $this->pendingAttachmentsQuery($ticket, $user, $attachmentIds)
            ->update(['activity_id' => $activity->id]);
    }

    protected function pendingAttachmentsQuery(Ticket $ticket, ?Authenticatable $user, array $attachmentIds): Builder
    {
        return TicketPlugin::resolveModelClass(TicketAttachment::class)::query()
            ->whereIn('id', $attachmentIds)
            ->where('ticket_id', $ticket->getKey())
            ->where('created_by', $user?->getAuthIdentifier())
            ->whereNull('activity_id');
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
