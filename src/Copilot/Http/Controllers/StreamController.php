<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Padmission\Tickets\Copilot\Agent\CopilotAgent;
use Padmission\Tickets\Copilot\CopilotPlugin;
use Padmission\Tickets\Copilot\Events\CopilotAiCallCompleted;
use Padmission\Tickets\Copilot\Events\CopilotMessageSent;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Copilot\Schemas\BlockValidator;
use Padmission\Tickets\Copilot\Services\ConversationManager;
use Padmission\Tickets\Copilot\Services\EscalationDetector;
use Padmission\Tickets\Copilot\Services\RateLimitService;
use Padmission\Tickets\Copilot\Services\ToolRegistry;
use Padmission\Tickets\Copilot\Services\ToolTraceRecorder;
use Padmission\Tickets\Models\TicketActivity;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StreamController
{
    protected string $buffer = '';

    protected bool $aborted = false;

    protected ?TicketActivity $aiActivity = null;

    public function __construct(
        protected BlockValidator $blockValidator,
        protected EscalationDetector $escalationDetector,
        protected ToolTraceRecorder $toolTraceRecorder,
    ) {}

    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'string'],
            'panel_id' => ['required', 'string'],
            'context' => ['nullable', 'array'],
            'context.page_title' => ['nullable', 'string', 'max:255'],
            'context.page_url' => ['nullable', 'string', 'max:2048'],
            'context.record' => ['nullable', 'array'],
            'context.record.type' => ['nullable', 'string', 'max:80'],
            'context.record.id' => ['nullable', 'string', 'max:80'],
            'context.record.label' => ['nullable', 'string', 'max:255'],
            'context.record.subtitle' => ['nullable', 'string', 'max:255'],
        ]);

        $panelId = $request->string('panel_id')->toString();

        try {
            Filament::setCurrentPanel($panelId);
        } catch (Throwable) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $user = Filament::auth()->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        /** @var CopilotPlugin $plugin */
        $plugin = CopilotPlugin::get();

        if (! $plugin->isAuthorized($user)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $tenant = $this->resolveTenant($user);
        $content = $request->string('message')->toString();
        $conversationId = $request->input('conversation_id');
        $requestContext = (array) $request->input('context', []);

        /** @var RateLimitService $rateLimitService */
        $rateLimitService = app(RateLimitService::class);

        if (config('filament-copilot.rate_limits.enabled') && ! $rateLimitService->canSendMessage($user, $panelId, $tenant)) {
            return $this->sseResponse(function (): void {
                $this->sendSseEvent('error', ['message' => __('filament-copilot::filament-copilot.rate_limit_exceeded')]);
                $this->sendSseEvent('done', []);
            });
        }

        /** @var ConversationManager $conversationManager */
        $conversationManager = app(ConversationManager::class);

        try {
            if ($conversationId) {
                $conversation = CopilotConversation::query()
                    ->forPanel($panelId)
                    ->forParticipant($user)
                    ->forTenant($tenant)
                    ->find($conversationId);

                if (! $conversation) {
                    return $this->sseResponse(function (): void {
                        $this->sendSseEvent('error', ['message' => 'Conversation not found.']);
                        $this->sendSseEvent('done', []);
                    });
                }
            } else {
                $conversation = $conversationManager->create($user, $panelId, $tenant);
            }

            $userMessage = $conversationManager->addUserMessage($conversation, $content);
            event(new CopilotMessageSent($conversation, $content, $panelId));
        } catch (Throwable $exception) {
            report($exception);

            return $this->sseResponse(function (): void {
                $this->sendSseEvent('error', ['message' => 'Support AI could not start. Please try again or open a ticket.']);
                $this->sendSseEvent('done', []);
            });
        }

        return $this->sseResponse(function () use ($conversation, $conversationManager, $user, $panelId, $tenant, $rateLimitService, $plugin, $content, $requestContext): void {
            $startedAt = microtime(true);
            $provider = $plugin->getProvider();
            $model = $plugin->getModel();

            $this->aiActivity = $conversationManager->createAiActivity($conversation, $provider, $model);
            $conversation->ticket?->update([
                'ai_provider' => $provider,
                'ai_model' => $model,
            ]);

            $this->sendSseEvent('start', [
                'conversation_id' => $conversation->id,
                'ticket_id' => $conversation->ticket_id,
                'ai_activity_id' => $this->aiActivity->id,
            ]);

            try {
                app()->instance('filament-copilot.request_context', $requestContext);
                config(['filament-copilot.request_context' => $requestContext]);

                /** @var ToolRegistry $toolRegistry */
                $toolRegistry = app(ToolRegistry::class);

                /** @var CopilotAgent $agent */
                $agent = app(CopilotAgent::class);

                $messages = $conversationManager->getMessagesForAgent($conversation);
                $lastUserMessage = '';

                if (! empty($messages) && end($messages)['role'] === 'user') {
                    $lastUserMessage = array_pop($messages)['content'];
                }

                $lastUserMessage = $this->withRequestContext($lastUserMessage, $requestContext);

                $agent->forPanel($panelId)
                    ->forUser($user)
                    ->forTenant($tenant)
                    ->withTools($toolRegistry->buildTools($panelId, $user, $tenant, $conversation->id))
                    ->withMessages($messages)
                    ->withSystemPrompt($plugin->getSystemPrompt());

                $streamResponse = $agent->stream(
                    prompt: $lastUserMessage,
                    provider: $provider,
                    model: $model,
                );

                $usage = null;

                foreach ($streamResponse as $event) {
                    if ($this->aborted) {
                        break;
                    }

                    if ($event instanceof TextDelta) {
                        $this->handleTextDelta((string) $event->delta);
                    } elseif ($event instanceof ToolCall) {
                        $this->handleToolCall($event);
                    } elseif ($event instanceof ToolResult) {
                        $this->handleToolResult($event);
                    } elseif ($event instanceof StreamEnd) {
                        $this->flushResidualBuffer();
                        $usage = $event->usage;
                    }
                }

                $usage ??= $streamResponse->usage;
                $escalationReason = $this->escalationDetector->detect($this->aiActivity, $content);
                $data = $this->aiActivity->data ?? [];
                $data['status'] = $this->aborted ? 'failed' : 'complete';
                $data['input_tokens'] = $usage->promptTokens ?? 0;
                $data['output_tokens'] = $usage->completionTokens ?? 0;
                $data['provider'] = $provider;
                $data['model'] = $model;
                $data['escalation_reason'] = $escalationReason;
                $this->aiActivity->forceFill(['data' => $data])->save();

                if (config('filament-copilot.rate_limits.enabled')) {
                    $rateLimitService->recordTokenUsage(
                        user: $user,
                        panelId: $panelId,
                        inputTokens: $usage->promptTokens ?? 0,
                        outputTokens: $usage->completionTokens ?? 0,
                        tenant: $tenant,
                        conversationId: $conversation->id,
                        model: $model,
                        provider: $provider,
                    );
                }

                event(new CopilotAiCallCompleted(
                    model: (string) $model,
                    inputTokens: $usage->promptTokens ?? 0,
                    outputTokens: $usage->completionTokens ?? 0,
                    durationMs: (int) ((microtime(true) - $startedAt) * 1000),
                    success: ! $this->aborted,
                    ticketId: $conversation->ticket_id,
                    userId: $user->getKey(),
                    tenantId: $tenant?->getKey(),
                ));

                $this->sendSseEvent('complete', [
                    'total_input_tokens' => $usage->promptTokens ?? 0,
                    'total_output_tokens' => $usage->completionTokens ?? 0,
                    'escalation_reason' => $escalationReason,
                ]);
                $this->sendSseEvent('done', []);
            } catch (Throwable $e) {
                if ($this->aiActivity) {
                    $data = $this->aiActivity->data ?? [];
                    $data['status'] = 'failed';
                    $data['error'] = $e->getMessage();
                    $this->aiActivity->forceFill(['data' => $data])->save();
                }

                $this->sendSseEvent('error', ['message' => 'Support AI could not finish the response.']);
                $this->sendSseEvent('done', []);
            } finally {
                app()->forgetInstance('filament-copilot.request_context');
                config(['filament-copilot.request_context' => null]);
            }
        });
    }

    protected function handleTextDelta(string $delta): void
    {
        $this->buffer .= $delta;

        while (($nlPos = strpos($this->buffer, "\n")) !== false) {
            $line = trim(substr($this->buffer, 0, $nlPos));
            $this->buffer = substr($this->buffer, $nlPos + 1);
            $this->processLine($line);
        }
    }

    protected function flushResidualBuffer(): void
    {
        $residual = trim($this->buffer);
        $this->buffer = '';

        if ($residual !== '') {
            $this->processLine($residual);
        }
    }

    protected function processLine(string $line): void
    {
        if ($line === '' || ! $this->aiActivity) {
            return;
        }

        if (preg_match('/^```/', $line)) {
            $this->aiActivity->recordParseWarning($line, 'fence-wrapper-stripped');

            return;
        }

        if (str_starts_with($line, '[')) {
            $this->sendSseEvent('error', ['message' => 'Top-level array detected - expected NDJSON.']);
            $this->aborted = true;

            return;
        }

        try {
            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($decoded) && array_is_list($decoded)) {
                $this->sendSseEvent('error', ['message' => 'Top-level array detected - expected NDJSON.']);
                $this->aborted = true;

                return;
            }

            $this->blockValidator->assert($decoded);

            if (($decoded['kind'] ?? null) === 'Meta') {
                $this->aiActivity->persistAiMeta($decoded['props']);

                return;
            }

            $this->sendSseEvent('block', $decoded);
            $this->aiActivity->appendAiBlock($decoded);
        } catch (Throwable $e) {
            $this->aiActivity->recordParseError($line, $e->getMessage());
        }
    }

    protected function handleToolCall(ToolCall $event): void
    {
        if ($this->aiActivity) {
            $this->toolTraceRecorder->recordPending(
                activity: $this->aiActivity,
                callId: $event->toolCall->id,
                name: $event->toolCall->name,
                args: $event->toolCall->arguments,
            );
        }

        $this->sendSseEvent('tool_call_start', [
            'name' => $event->toolCall->name,
            'args' => $event->toolCall->arguments,
        ]);
    }

    protected function handleToolResult(ToolResult $event): void
    {
        if ($this->aiActivity) {
            $this->toolTraceRecorder->recordResult(
                activity: $this->aiActivity,
                callId: $event->toolResult->id,
                result: $event->toolResult->result,
                successful: $event->successful,
                error: $event->error,
            );
        }

        $this->sendSseEvent('tool_call_result', [
            'name' => $event->toolResult->name ?? '',
            'status' => $event->successful ? 'success' : 'failed',
        ]);
    }

    protected function sseResponse(callable $callback): StreamedResponse
    {
        return new StreamedResponse(function () use ($callback): void {
            if (ob_get_level()) {
                ob_end_clean();
            }

            $callback();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function sendSseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level()) {
            ob_flush();
        }

        flush();
    }

    protected function withRequestContext(string $message, array $context): string
    {
        $record = $context['record'] ?? null;
        $lines = [];

        if (is_array($record) && filled($record['type'] ?? null) && filled($record['id'] ?? null)) {
            $lines[] = 'Current record context:';
            $lines[] = '- type: '.(string) $record['type'];
            $lines[] = '- id: '.(string) $record['id'];
            $lines[] = '- label: '.(string) ($record['label'] ?? '');
            $lines[] = '- subtitle: '.(string) ($record['subtitle'] ?? '');
            $lines[] = 'For questions about this selected record, use the exact type and id above when calling record tools. Do not pass words from the label, person name, page title, or user question as record IDs.';
        }

        if (filled($context['page_title'] ?? null) || filled($context['page_url'] ?? null)) {
            $lines[] = 'Current page: '.trim(((string) ($context['page_title'] ?? '')).' '.((string) ($context['page_url'] ?? '')));
        }

        if ($lines === []) {
            return $message;
        }

        return implode("\n", $lines)."\nUse this context only to choose which configured read-only tools to call; do not treat it as authoritative record data.\n\nUser question:\n".$message;
    }

    protected function resolveTenant(mixed $user): ?Model
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Model) {
            return $tenant;
        }

        if (! config('padmission-tickets.tenancy.enabled', false) || ! $user instanceof Model) {
            return null;
        }

        $tenantModel = config('padmission-tickets.tenancy.tenancy_model');

        if (! is_string($tenantModel) || ! is_subclass_of($tenantModel, Model::class)) {
            return null;
        }

        $tenantKey = Str::snake(class_basename($tenantModel)).'_id';
        $tenantId = $user->getAttribute($tenantKey);

        if (! $tenantId) {
            return null;
        }

        return $tenantModel::query()->find($tenantId);
    }
}
