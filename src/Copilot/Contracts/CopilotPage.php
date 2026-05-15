<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Contracts;

use Laravel\Ai\Contracts\Tool;

interface CopilotPage
{
    /**
     * A description of what this page shows, shown to the AI agent.
     */
    public static function copilotPageDescription(): ?string;

    /**
     * Return the copilot tools available for this page.
     *
     * @return array<Tool>
     */
    public static function copilotTools(): array;
}
