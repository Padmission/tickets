<?php

declare(strict_types=1);

namespace Padmission\Tickets\Copilot\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Padmission\Tickets\Copilot\Services\ToolTraceRecorder;
use Stringable;
use Throwable;

class TraceableTool implements Tool
{
    public function __construct(
        protected Tool $tool,
        protected string $conversationId,
    ) {}

    public function name(): string
    {
        return is_callable([$this->tool, 'name'])
            ? $this->tool->name()
            : class_basename($this->tool);
    }

    public function description(): Stringable|string
    {
        return $this->tool->description();
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return $this->tool->handle($request);
        } catch (Throwable $exception) {
            app(ToolTraceRecorder::class)->failLatestPending(
                conversationId: $this->conversationId,
                toolName: $this->name(),
                exception: $exception,
            );

            throw $exception;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }
}
