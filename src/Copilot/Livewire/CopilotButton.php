<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Livewire;

use Padmission\Tickets\Copilot\CopilotPlugin;
use Livewire\Component;

class CopilotButton extends Component
{
    public array $quickActions = [];

    public function mount(): void
    {
        try {
            $plugin = CopilotPlugin::get();
            $this->quickActions = $plugin->getQuickActions();
        } catch (\Throwable) {
            $this->quickActions = config('filament-copilot.quick_actions', []);
        }
    }

    public function openCopilot(): void
    {
        $this->dispatch('copilot-open');
    }

    public function render()
    {
        return view('filament-copilot::livewire.copilot-button');
    }
}
