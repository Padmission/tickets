<?php

declare(strict_types=1);

namespace Padmission\Tickets\Copilot\Services;

use Carbon\CarbonImmutable;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\TicketActivity;
use Throwable;

class ToolTraceRecorder
{
    public function recordPending(TicketActivity $activity, string $callId, string $name, array $args): void
    {
        $activity->appendToolTrace([
            'call_id' => $callId,
            'name' => $name,
            'args' => $args,
            'started_at' => now()->toISOString(),
            'status' => 'pending',
        ]);
    }

    public function recordResult(TicketActivity $activity, string $callId, mixed $result, bool $successful, ?string $error = null): void
    {
        $data = $activity->data ?? [];
        $tools = $data['trace_tools'] ?? [];

        foreach ($tools as &$tool) {
            if (($tool['call_id'] ?? null) !== $callId) {
                continue;
            }

            $tool['status'] = $successful ? 'success' : 'failed';
            $tool['completed_at'] = now()->toISOString();
            $tool['duration_ms'] = $this->durationMs($tool['started_at'] ?? null);
            $tool['result'] = $this->normalizeResult($result);

            if ($error !== null) {
                $tool['error'] = $error;
            }
        }

        $data['trace_tools'] = $tools;
        $activity->forceFill(['data' => $data])->save();
    }

    public function failLatestPending(string $conversationId, string $toolName, Throwable $exception): void
    {
        $conversation = CopilotConversation::query()
            ->withoutCopilotTenant()
            ->with('ticket')
            ->find($conversationId);

        $ticket = $conversation?->ticket;

        if (! $ticket) {
            return;
        }

        $activity = $ticket->ticketActivities()
            ->where('sender', ActivitySender::Ai)
            ->where('type', ActivityType::Message)
            ->latest('id')
            ->get()
            ->first(fn (TicketActivity $activity): bool => $this->hasPendingTool($activity, $toolName));

        if (! $activity) {
            return;
        }

        $data = $activity->data ?? [];
        $tools = $data['trace_tools'] ?? [];

        for ($index = count($tools) - 1; $index >= 0; $index--) {
            if (($tools[$index]['name'] ?? null) !== $toolName || ($tools[$index]['status'] ?? null) !== 'pending') {
                continue;
            }

            $tools[$index]['status'] = 'failed';
            $tools[$index]['completed_at'] = now()->toISOString();
            $tools[$index]['duration_ms'] = $this->durationMs($tools[$index]['started_at'] ?? null);
            $tools[$index]['error'] = $exception->getMessage();

            break;
        }

        $data['trace_tools'] = $tools;
        $activity->forceFill(['data' => $data])->save();
    }

    protected function hasPendingTool(TicketActivity $activity, string $toolName): bool
    {
        foreach (($activity->data['trace_tools'] ?? []) as $tool) {
            if (($tool['name'] ?? null) === $toolName && ($tool['status'] ?? null) === 'pending') {
                return true;
            }
        }

        return false;
    }

    protected function durationMs(?string $startedAt): int
    {
        if (! $startedAt) {
            return 0;
        }

        return (int) max(0, CarbonImmutable::parse($startedAt)->diffInMilliseconds(now()));
    }

    protected function normalizeResult(mixed $result): mixed
    {
        if (is_scalar($result) || is_array($result) || $result === null) {
            return $result;
        }

        if ($result instanceof \Stringable) {
            return (string) $result;
        }

        return json_decode(json_encode($result, JSON_UNESCAPED_UNICODE), true);
    }
}
