<?php

namespace Padmission\Tickets\Filament\Livewire\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\TicketPlugin;

class SupportPanel extends Component
{
    public string $activeTab = 'ask';

    public ?int $activeTicketId = null;

    public string $message = '';

    public ?string $streamConversationId = null;

    public ?int $streamActivityId = null;

    public ?string $streamError = null;

    public bool $isStreaming = false;

    public array $currentRecordContext = [];

    public bool $editingRecordContext = false;

    public string $recordTypeInput = '';

    public string $recordIdInput = '';

    public ?int $feedbackActivityId = null;

    public string $feedbackReason = '';

    public function sendMessage(): void
    {
        $message = trim($this->message);

        if ($message === '') {
            return;
        }

        $this->message = '';
        $this->streamError = null;
        $conversationId = $this->conversationIdForAiMessage();

        if ($this->sendHumanMessage($message)) {
            return;
        }

        $this->isStreaming = true;

        $this->dispatch('padmission-support-stream', payload: [
            'url' => route('filament-copilot.stream'),
            'message' => $message,
            'conversation_id' => $conversationId,
            'panel_id' => Filament::getCurrentOrDefaultPanel()->getId(),
            'context' => [
                'record' => $this->currentRecordContext ?: null,
            ],
        ]);
    }

    public function setCurrentContext(array $context): void
    {
        $type = (string) ($context['type'] ?? '');
        $id = (string) ($context['id'] ?? '');

        if ($type === '' || $id === '') {
            $this->currentRecordContext = [];
            $this->recordIdInput = '';

            return;
        }

        $this->currentRecordContext = [
            'type' => $type,
            'id' => $id,
            'label' => (string) ($context['label'] ?? $this->contextTypeLabel($type).' #'.$id),
            'subtitle' => (string) ($context['subtitle'] ?? ''),
            'mark' => (string) ($context['mark'] ?? ''),
            'url' => (string) ($context['url'] ?? ''),
        ];

        $this->recordTypeInput = $type;
        $this->recordIdInput = $id;
        $this->editingRecordContext = false;
    }

    public function editRecordContext(): void
    {
        $this->recordTypeInput = (string) ($this->currentRecordContext['type'] ?? $this->recordTypeInput);
        $this->recordIdInput = (string) ($this->currentRecordContext['id'] ?? $this->recordIdInput);
        $this->editingRecordContext = true;
    }

    public function applyRecordContext(): void
    {
        $type = trim($this->recordTypeInput);
        $id = trim($this->recordIdInput);

        if ($type === '' || $id === '') {
            $this->currentRecordContext = [];
            $this->editingRecordContext = false;

            return;
        }

        $this->setCurrentContext([
            'type' => $type,
            'id' => $id,
            'label' => $this->contextTypeLabel($type).' #'.$id,
            'subtitle' => 'Selected manually',
        ]);
    }

    public function clearRecordContext(): void
    {
        $this->currentRecordContext = [];
        $this->recordIdInput = '';
        $this->editingRecordContext = false;
    }

    public function selectTicket(int $ticketId): void
    {
        $this->activeTicketId = $ticketId;
        $this->activeTab = 'ask';
    }

    public function openTicket(): void
    {
        $ticket = $this->activeTicket();

        if (! $ticket) {
            return;
        }

        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);
        $openStatus = $statusModel::query()->where('seed_key', 'open')->first();

        if (! $openStatus) {
            return;
        }

        $strategy = TicketPlugin::get()->getAssignmentStrategy();

        if ($strategy && ! $ticket->assignee_id) {
            $strategy->assign($ticket);
        }

        $ticket->status_id = $openStatus->getKey();
        $ticket->save();

        $ticket->addTicketActivity(
            type: ActivityType::Escalated,
            sender: ActivitySender::System,
            userId: auth()->id(),
            data: ['from' => 'ai_in_progress', 'to' => 'open'],
        );

        if ($ticket->assignee_id) {
            event(new TicketAssignedEvent($ticket, auth()->user()));
        }
    }

    public function markResolved(): void
    {
        $ticket = $this->activeTicket();

        if (! $ticket || $this->isAiConversationTicket($ticket) || $ticket->isClosed) {
            return;
        }

        $ticket->close(closedById: auth()->id());
    }

    public function startAiFeedback(int $activityId): void
    {
        $activity = $this->aiActivityForFeedback($activityId);

        if (! $activity) {
            return;
        }

        $this->feedbackActivityId = $activity->id;
        $this->feedbackReason = (string) data_get($activity->data, 'feedback.reason', '');
    }

    public function cancelAiFeedback(): void
    {
        $this->reset('feedbackActivityId', 'feedbackReason');
    }

    public function submitAiFeedback(): void
    {
        $this->validate([
            'feedbackActivityId' => ['required', 'integer'],
            'feedbackReason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $activity = $this->aiActivityForFeedback((int) $this->feedbackActivityId);

        if (! $activity) {
            $this->reset('feedbackActivityId', 'feedbackReason');

            return;
        }

        $activity->flagAiAnswerAsIncorrect(
            reason: trim($this->feedbackReason),
            userId: auth()->id(),
            context: [
                'record' => $this->currentRecordContext ?: null,
                'page_url' => request()->headers->get('referer') ?: url()->previous(),
            ],
        );

        $this->reset('feedbackActivityId', 'feedbackReason');
    }

    #[On('padmission-support-stream-started')]
    public function bindStream(string $conversationId, int $activityId, int $ticketId): void
    {
        $this->isStreaming = true;
        $this->streamConversationId = $conversationId;
        $this->streamActivityId = $activityId;
        $this->activeTicketId = $ticketId;
    }

    #[On('padmission-support-stream-complete')]
    public function refreshAfterStream(): void
    {
        $this->isStreaming = false;
    }

    #[On('padmission-support-stream-error')]
    public function showStreamError(string $message): void
    {
        $this->isStreaming = false;
        $this->streamError = $message;
        $this->activeTab = 'ask';
    }

    public function render(): View
    {
        return view('padmission-tickets::filament.livewire.support-panel', [
            'tickets' => $this->tickets(),
            'conversations' => $this->conversations(),
            'activities' => $this->activities(),
            'activeTicket' => $this->activeTicket(),
            'counts' => $this->counts(),
            'recordContextOptions' => $this->recordContextOptions(),
        ]);
    }

    protected function tickets()
    {
        return $this->baseTicketQuery()
            ->where(fn (Builder $query): Builder => $query
                ->whereDoesntHave('status')
                ->orWhereHas('status', fn (Builder $status): Builder => $status->where('seed_key', '!=', 'ai_in_progress'))
            )
            ->with(['status', 'latestMessage', 'latestUserMessage'])
            ->latest('updated_at')
            ->limit(30)
            ->get();
    }

    protected function conversations()
    {
        return $this->baseTicketQuery()
            ->whereHas('status', fn (Builder $status): Builder => $status->where('seed_key', 'ai_in_progress'))
            ->with(['status', 'latestUserMessage'])
            ->latest('updated_at')
            ->limit(30)
            ->get();
    }

    protected function activities()
    {
        $ticket = $this->activeTicket();

        if (! $ticket) {
            return collect();
        }

        return app(TicketActivityService::class)
            ->getActivities($ticket, view: 'support')
            ->reverse()
            ->values();
    }

    protected function activeTicket(): ?Ticket
    {
        if (! $this->activeTicketId) {
            return null;
        }

        return TicketPlugin::get()->getTicketQuery()
            ->where('submitter_id', auth()->id())
            ->with('status')
            ->find($this->activeTicketId);
    }

    protected function counts(): array
    {
        $tickets = $this->tickets();
        $conversations = $this->conversations();

        return [
            'all' => $tickets->count(),
            'conversations' => $conversations->count(),
            'open' => $tickets->whereNull('closed_at')->count(),
            'resolved' => $tickets->whereNotNull('closed_at')->count(),
        ];
    }

    protected function baseTicketQuery()
    {
        return TicketPlugin::get()->getTicketQuery()
            ->where('submitter_id', auth()->id());
    }

    protected function sendHumanMessage(string $message): bool
    {
        $ticket = $this->activeTicket();

        if (! $ticket || $this->isAiConversationTicket($ticket)) {
            return false;
        }

        if ($ticket->isClosed) {
            $this->isStreaming = false;

            return true;
        }

        $ticket->addTicketActivity(
            type: ActivityType::Message,
            sender: ActivitySender::User,
            userId: auth()->id(),
            content: $message,
        );

        $ticket->forceFill([
            'turn' => Turn::Supporter,
            'updated_at' => now(),
        ])->save();

        $this->isStreaming = false;

        return true;
    }

    protected function conversationIdForAiMessage(): ?string
    {
        $ticket = $this->activeTicket();

        if ($ticket && $this->isAiConversationTicket($ticket)) {
            $conversationId = CopilotConversation::query()
                ->where('ticket_id', $ticket->getKey())
                ->value('id');

            if (is_string($conversationId)) {
                $this->streamConversationId = $conversationId;

                return $conversationId;
            }
        }

        return $this->streamConversationId;
    }

    protected function isAiConversationTicket(Ticket $ticket): bool
    {
        if ($ticket->status?->seed_key === 'ai_in_progress') {
            return true;
        }

        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);

        return $statusModel::query()
            ->whereKey($ticket->status_id)
            ->where('seed_key', 'ai_in_progress')
            ->exists();
    }

    protected function aiActivityForFeedback(int $activityId): ?TicketActivity
    {
        $ticket = $this->activeTicket();

        if (! $ticket) {
            return null;
        }

        return $ticket->ticketActivities()
            ->whereKey($activityId)
            ->where('type', ActivityType::Message)
            ->where('sender', ActivitySender::Ai)
            ->first();
    }

    protected function recordContextOptions(): array
    {
        return config('filament-copilot.record_context.types', []);
    }

    protected function contextTypeLabel(string $type): string
    {
        return $this->recordContextOptions()[$type] ?? Str::headline($type);
    }
}
