<?php

namespace Padmission\Tickets\Copilot\Services;

use Illuminate\Support\Collection;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\TicketPlugin;

class AiFeedbackExportService
{
    public function toJsonLines(): string
    {
        return $this->records()
            ->map(fn (array $record): string => json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->implode("\n");
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    public function records(): Collection
    {
        return TicketPlugin::get()->getTicketQuery()
            ->whereHas('ticketActivities', fn ($query) => $this->flaggedAiActivityQuery($query))
            ->with([
                'copilotConversation',
                'ticketActivities' => fn ($query) => $query->oldest('created_at'),
            ])
            ->latest('updated_at')
            ->get()
            ->flatMap(fn (Ticket $ticket): Collection => $this->recordsForTicket($ticket))
            ->values();
    }

    protected function flaggedAiActivityQuery($query): void
    {
        $query
            ->where('type', ActivityType::Message->value)
            ->where('sender', ActivitySender::Ai->value)
            ->where('data->feedback->incorrect', true);
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    protected function recordsForTicket(Ticket $ticket): Collection
    {
        return $ticket->ticketActivities
            ->filter(fn (TicketActivity $activity): bool => $activity->sender === ActivitySender::Ai
                && $activity->type === ActivityType::Message
                && data_get($activity->data, 'feedback.incorrect') === true)
            ->map(fn (TicketActivity $activity): array => [
                'feedback' => data_get($activity->data, 'feedback'),
                'ticket' => [
                    'id' => $ticket->getKey(),
                    'subject' => $ticket->subject,
                    'status_id' => $ticket->status_id,
                    'panel' => $ticket->panel ?? null,
                    'source_panel' => $ticket->source_panel ?? null,
                    'created_at' => $ticket->created_at?->toIso8601String(),
                    'updated_at' => $ticket->updated_at?->toIso8601String(),
                ],
                'conversation_id' => $ticket->copilotConversation?->id,
                'user_question' => $this->previousUserQuestion($ticket, $activity),
                'ai_activity' => [
                    'id' => $activity->getKey(),
                    'created_at' => $activity->created_at?->toIso8601String(),
                    'content' => $activity->content,
                    'blocks' => data_get($activity->data, 'blocks', []),
                    'trace_tools' => data_get($activity->data, 'trace_tools', []),
                    'confidence' => data_get($activity->data, 'confidence'),
                    'escalation_reason' => data_get($activity->data, 'escalation_reason'),
                    'parse_errors' => data_get($activity->data, 'parse_errors', []),
                    'parse_warnings' => data_get($activity->data, 'parse_warnings', []),
                ],
            ]);
    }

    protected function previousUserQuestion(Ticket $ticket, TicketActivity $activity): ?array
    {
        $question = $ticket->ticketActivities
            ->filter(fn (TicketActivity $candidate): bool => $candidate->sender === ActivitySender::User
                && $candidate->type === ActivityType::Message
                && $candidate->created_at <= $activity->created_at)
            ->sortByDesc('created_at')
            ->first();

        if (! $question) {
            return null;
        }

        return [
            'id' => $question->getKey(),
            'content' => $question->content,
            'created_at' => $question->created_at?->toIso8601String(),
        ];
    }
}
