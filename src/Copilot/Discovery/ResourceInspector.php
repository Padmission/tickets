<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Discovery;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Padmission\Tickets\Copilot\Contracts\CopilotResource;
use Padmission\Tickets\Copilot\CopilotPlugin;

class ResourceInspector
{
    /**
     * Discover all resources in the panel that implement CopilotResource.
     */
    public function discoverResources(?string $panelId = null): array
    {
        $panel = $panelId
            ? Filament::getPanel($panelId)
            : Filament::getCurrentPanel();

        if (! $panel) {
            return [];
        }

        $resources = [];

        foreach ($panel->getResources() as $resourceClass) {
            if (! is_subclass_of($resourceClass, CopilotResource::class)) {
                continue;
            }

            if ($this->shouldRespectAuthorization() && ! $this->canAccessResource($resourceClass)) {
                continue;
            }

            /** @var class-string<\Filament\Resources\Resource&CopilotResource> $resourceClass */
            $hasTools = false;
            try {
                $hasTools = ! empty($resourceClass::copilotTools());
                $description = $resourceClass::copilotResourceDescription();
            } catch (\Throwable) {
                $description = null;
            }

            $resources[] = [
                'resource' => $resourceClass,
                'label' => $resourceClass::getModelLabel(),
                'plural_label' => $resourceClass::getPluralModelLabel(),
                'slug' => $resourceClass::getSlug(),
                'copilot_description' => $description,
                'has_tools' => $hasTools,
            ];
        }

        return $resources;
    }

    protected function shouldRespectAuthorization(): bool
    {
        return CopilotPlugin::get()->shouldRespectAuthorization();
    }

    protected function canAccessResource(string $resourceClass): bool
    {
        if (! method_exists($resourceClass, 'canAccess')) {
            return true;
        }

        try {
            return (bool) $resourceClass::canAccess();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build AI-friendly resource descriptions for the system prompt.
     */
    public function buildResourceContext(?string $panelId = null): string
    {
        $resources = $this->discoverResources($panelId);

        if (empty($resources)) {
            return '';
        }

        $lines = ['## Available Resources'];

        foreach ($resources as $resource) {
            $line = '- '.$resource['plural_label'].' ('.$resource['resource'].')';

            if (! empty($resource['copilot_description'])) {
                $line .= ': '.$resource['copilot_description'];
            }

            if ($resource['has_tools']) {
                $line .= ' [has copilot tools]';
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
