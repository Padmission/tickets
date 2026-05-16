<?php

namespace Padmission\Tickets\Filament\Livewire\Support;

use Livewire\Component;
use Padmission\Tickets\TicketPlugin;

class SupportButton extends Component
{
    public function getOpenTicketCountProperty(): int
    {
        return TicketPlugin::get()->getTicketQuery()
            ->open()
            ->where('submitter_id', auth()->id())
            ->count();
    }

    public function render()
    {
        return view('padmission-tickets::filament.livewire.support-button');
    }
}
