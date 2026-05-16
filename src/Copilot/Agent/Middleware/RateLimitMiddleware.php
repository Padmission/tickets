<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Agent\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Prompts\AgentPrompt;
use Padmission\Tickets\Copilot\Events\CopilotRateLimitExceeded;
use Padmission\Tickets\Copilot\Services\RateLimitService;

class RateLimitMiddleware
{
    public function __construct(
        protected string $panelId,
        protected Model $user,
        protected ?Model $tenant = null,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        if (! config('filament-copilot.rate_limits.enabled', false)) {
            return $next($prompt);
        }

        /** @var RateLimitService $service */
        $service = app(RateLimitService::class);

        if (! $service->canSendMessage($this->user, $this->panelId, $this->tenant)) {
            event(new CopilotRateLimitExceeded($this->user, $this->panelId, $this->tenant));

            throw new \RuntimeException('Rate limit exceeded. Please try again later.');
        }

        $response = $next($prompt);

        $service->recordMessage($this->user, $this->panelId, $this->tenant);

        return $response;
    }
}
