<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Padmission\Tickets\Copilot\Discovery\PageInspector;
use Stringable;

class ListPagesTool extends BaseTool
{
    public function __construct(
        protected PageInspector $pageInspector,
    ) {}

    public function description(): Stringable|string
    {
        return 'List all available pages in the current panel with their descriptions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $pages = $this->pageInspector->discoverPages($this->panelId ?? null);

        if (empty($pages)) {
            return 'No pages available in this panel.';
        }

        $lines = ['Available Pages:', ''];

        foreach ($pages as $page) {
            $line = '- '.$page['label'].' ('.$page['page'].')';

            if (! empty($page['copilot_description'])) {
                $line .= ' — '.$page['copilot_description'];
            }

            if (! empty($page['has_tools'])) {
                $line .= ' [has copilot tools]';
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
