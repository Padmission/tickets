<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Contracts;

use Laravel\Ai\Contracts\Tool;

interface CopilotResource
{
    /**
     * A description of what this resource manages, shown to the AI agent.
     */
    public static function copilotResourceDescription(): ?string;

    /**
     * Return the copilot tools available for this resource.
     *
     * @return array<Tool>
     */
    public static function copilotTools(): array;
}
