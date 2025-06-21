<?php

namespace Padmission\Tickets\Services;

use Illuminate\Contracts\Support\Htmlable;
use Padmission\Tickets\Models\Ticket;

class EmailLogoService
{
    /**
     * Get the email logo for a ticket
     */
    public function getEmailLogo(Ticket $ticket): string | Htmlable | null
    {
        $panelId = $ticket->panel;
        if (! $panelId) {
            return null;
        }

        // Try tenant logo first
        if ($logo = $this->getTenantLogo()) {
            return $logo;
        }

        // Fallback to panel brand logo
        return $this->getPanelLogo($panelId);
    }

    /**
     * Get tenant logo if available
     */
    protected function getTenantLogo(): ?string
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (! $tenant || ! method_exists($tenant, 'getLogo')) {
            return null;
        }

        try {
            $logo = $tenant->getLogo();

            return $this->formatLogo($logo);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get panel brand logo
     */
    protected function getPanelLogo(string $panelId): ?string
    {
        $panel = \Filament\Facades\Filament::getPanel($panelId);
        if (! $panel || ! ($logo = $panel->getBrandLogo())) {
            return null;
        }

        $height = $panel->getBrandLogoHeight();

        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            return sprintf('<img src="%s" style="height: %s;" />', $logo, $height);
        }

        return $logo;
    }

    /**
     * Format logo based on its type (object, SVG string, URL, etc.)
     */
    protected function formatLogo(mixed $logo): ?string
    {
        if (is_object($logo)) {
            if (method_exists($logo, 'getLogo')) {
                return $logo->getLogo();
            }
            if (method_exists($logo, 'getUrl')) {
                return $logo->getUrl();
            }
        }

        if (is_string($logo)) {
            // Simple SVG check (starts with <svg)
            if (stripos($logo, '<svg') === 0) {
                return $logo; // Raw SVG
            }

            if (filter_var($logo, FILTER_VALIDATE_URL)) {
                return sprintf('<img src="%s" />', $logo);
            }

            return $logo;
        }

        return null;
    }
}
