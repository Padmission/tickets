<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Tools;

use Padmission\Tickets\Copilot\Discovery\WidgetInspector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListWidgetsTool extends BaseTool
{
    public function __construct(
        protected WidgetInspector $widgetInspector,
    ) {}

    public function description(): Stringable|string
    {
        return 'List all available widgets in the current panel with their descriptions. Use get_tools with a widget class to discover its copilot tools.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $widgets = $this->widgetInspector->discoverWidgets($this->panelId ?? null);

        if (empty($widgets)) {
            return 'No widgets available in this panel.';
        }

        $lines = ['Available Widgets:', ''];

        foreach ($widgets as $widget) {
            $line = '- ' . $widget['name'] . ' (' . $widget['widget'] . ')';

            if (! empty($widget['description'])) {
                $line .= '  ' . $widget['description'];
            }

            if (! empty($widget['has_tools'])) {
                $line .= ' [has copilot tools]';
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
