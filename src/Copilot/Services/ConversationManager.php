<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Padmission\Tickets\Actions\GetDefaultPriorityForPanel;
use Padmission\Tickets\Actions\GetDefaultStatusForPanel;
use Padmission\Tickets\Copilot\Events\CopilotConversationCreated;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class ConversationManager
{
    /**
     * Start a new conversation.
     */
    public function create(
        Model $user,
        string $panelId,
        ?Model $tenant = null,
        ?string $title = null,
    ): CopilotConversation {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);
        $aiStatus = $statusModel::getAiInProgressStatus();
        $priority = app(GetDefaultPriorityForPanel::class)($panelId);

        $ticketAttributes = [
            'panel' => $panelId,
            'source_panel' => $panelId,
            'subject' => $title ?? 'Ask AI: '.Str::limit('New Conversation', 60),
            'status_id' => $aiStatus?->getKey() ?? app(GetDefaultStatusForPanel::class)($panelId)->getKey(),
            'priority_id' => $priority->getKey(),
            'submitter_id' => $user->getKey(),
            'turn' => Turn::User,
            'data' => [],
        ];

        if (config('padmission-tickets.tenancy.enabled', false) && $tenant) {
            $tenantKey = Str::snake(class_basename(config('padmission-tickets.tenancy.tenancy_model'))).'_id';
            $ticketAttributes[$tenantKey] = $tenant->getKey();
        }

        /** @var Ticket $ticket */
        $ticket = $ticketModel::create($ticketAttributes);

        $conversation = CopilotConversation::create([
            'ticket_id' => $ticket->getKey(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'panel_id' => $panelId,
            'tenant_type' => $tenant?->getMorphClass(),
            'tenant_id' => $tenant?->getKey(),
            'title' => $title ?? 'New Conversation',
        ]);

        event(new CopilotConversationCreated($conversation));

        return $conversation;
    }

    /**
     * Add a user message to a conversation.
     */
    public function addUserMessage(CopilotConversation $conversation, string $content): TicketActivity
    {
        /** @var Ticket $ticket */
        $ticket = $conversation->ticket()->firstOrFail();

        $message = $ticket->addTicketActivity(
            type: ActivityType::Message,
            sender: ActivitySender::User,
            userId: $conversation->participant_id,
            content: $content,
        );

        $ticket->update([
            'subject' => $ticket->subject === 'Ask AI: New Conversation'
                ? 'Ask AI: '.$this->generateTitle($content)
                : $ticket->subject,
        ]);

        return $message;
    }

    public function createAiActivity(CopilotConversation $conversation, ?string $provider = null, ?string $model = null): TicketActivity
    {
        /** @var Ticket $ticket */
        $ticket = $conversation->ticket()->firstOrFail();

        return $ticket->addTicketActivity(
            type: ActivityType::Message,
            sender: ActivitySender::Ai,
            userId: null,
            data: [
                'kind' => 'ai_response',
                'status' => 'in_progress',
                'provider' => $provider,
                'model' => $model,
                'blocks' => [],
                'trace_tools' => [],
            ],
        );
    }

    /**
     * Add an assistant message to a conversation.
     */
    public function addAssistantMessage(
        CopilotConversation $conversation,
        string $content,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?array $metadata = null,
    ): TicketActivity {
        /** @var Ticket $ticket */
        $ticket = $conversation->ticket()->firstOrFail();

        return $ticket->addTicketActivity(
            type: ActivityType::Message,
            sender: ActivitySender::Ai,
            userId: null,
            content: $content,
            data: [
                'kind' => 'ai_response',
                'status' => 'complete',
                'blocks' => $metadata['blocks'] ?? [],
                'trace_tools' => $metadata['trace_tools'] ?? [],
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                ...($metadata ?? []),
            ],
        );
    }

    /**
     * Get conversations for a user in a panel.
     */
    public function getConversations(
        Model $user,
        string $panelId,
        ?Model $tenant = null,
        int $limit = 20,
    ) {
        return CopilotConversation::query()
            ->forPanel($panelId)
            ->forParticipant($user)
            ->forTenant($tenant)
            ->with('ticket.latestMessage')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get messages for a conversation in the format expected by the AI SDK.
     */
    public function getMessagesForAgent(CopilotConversation $conversation): array
    {
        $ticket = $conversation->ticket;

        if (! $ticket) {
            return [];
        }

        return $ticket->ticketActivities()
            ->where('type', ActivityType::Message)
            ->whereIn('sender', [ActivitySender::User, ActivitySender::Ai, ActivitySender::Supporter])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketActivity $message) => [
                'role' => $message->sender === ActivitySender::Ai ? 'assistant' : 'user',
                'content' => trim((string) ($message->content ?: $this->blocksToText($message->data['blocks'] ?? []))),
            ])
            ->filter(fn (array $message): bool => $message['content'] !== '')
            ->values()
            ->toArray();
    }

    /**
     * Get messages for chat rendering, including persisted tool calls.
     */
    public function getMessagesForChat(CopilotConversation $conversation): array
    {
        $ticket = $conversation->ticket;

        if (! $ticket) {
            return [];
        }

        return $ticket->ticketActivities()
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketActivity $activity) => [
                'role' => $activity->sender->value,
                'content' => $activity->content,
                'data' => $activity->data,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Delete a conversation and all related data.
     */
    public function delete(CopilotConversation $conversation): void
    {
        $conversation->delete();
    }

    /**
     * Update a conversation title.
     */
    public function updateTitle(CopilotConversation $conversation, string $title): void
    {
        $conversation->update(['title' => $title]);
    }

    /**
     * Generate a concise title from the first user message.
     */
    protected function generateTitle(string $content): string
    {
        // Take the first sentence or first 60 chars, whichever is shorter
        $content = trim($content);

        // Try to get first sentence
        if (preg_match('/^(.+?[.!?])\s/u', $content, $matches)) {
            $title = $matches[1];
        } else {
            $title = $content;
        }

        // Truncate to 60 characters max
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 57).'...';
        }

        return $title;
    }

    protected function blocksToText(array $blocks): string
    {
        return collect($blocks)
            ->map(fn (array $block): string => json_encode($block, JSON_UNESCAPED_UNICODE) ?: '')
            ->filter()
            ->implode("\n");
    }
}
