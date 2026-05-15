<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Services;

use Padmission\Tickets\Copilot\Tools\BaseTool;
use Padmission\Tickets\Copilot\Tools\GetToolsTool;
use Padmission\Tickets\Copilot\Tools\ListPagesTool;
use Padmission\Tickets\Copilot\Tools\ListResourcesTool;
use Padmission\Tickets\Copilot\Tools\ListWidgetsTool;
use Padmission\Tickets\Copilot\Tools\RecallTool;
use Padmission\Tickets\Copilot\Tools\RememberTool;
use Padmission\Tickets\Copilot\Tools\RunToolTool;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class ToolRegistry
{
    protected array $globalTools = [];

    protected array $toolClasses = [
        // Discovery
        ListResourcesTool::class,
        ListPagesTool::class,
        ListWidgetsTool::class,
        GetToolsTool::class,
        RunToolTool::class,
        // Memory
        RememberTool::class,
        RecallTool::class,
    ];

    /**
     * Register a global custom tool.
     */
    public function registerGlobal(string $toolClass): void
    {
        $this->globalTools[] = $toolClass;
    }

    /**
     * Build all tools configured for a panel/user context.
     */
    public function buildTools(string $panelId, Model $user, ?Model $tenant = null, ?string $conversationId = null): array
    {
        // Merge plugin-configured global tools
        $pluginTools = [];
        try {
            $plugin = \Padmission\Tickets\Copilot\CopilotPlugin::get();
            $pluginTools = $plugin->getGlobalTools();
        } catch (\Throwable) {
            $pluginTools = config('filament-copilot.global_tools', []);
        }

        $tools = [];

        foreach (array_merge($this->toolClasses, $this->globalTools, $pluginTools) as $toolClass) {
            $tool = app($toolClass);

            if ($tool instanceof BaseTool) {
                $tool->forPanel($panelId)
                    ->forUser($user)
                    ->forTenant($tenant);
            }

            if ($conversationId && $tool instanceof BaseTool) {
                $tool->forConversation($conversationId);
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Get the list of registered tool classes.
     */
    public function getToolClasses(): array
    {
        return array_merge($this->toolClasses, $this->globalTools);
    }
}
