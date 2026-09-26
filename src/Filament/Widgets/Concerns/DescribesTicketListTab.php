<?php

namespace Padmission\Tickets\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;

/*
 * Above the ticket list, a card describes the tab it sits over, using that
 * tab's own query (the one its badge counts) rather than the whole panel.
 * Anywhere else the list page passes no tab and the card stays panel-wide.
 */
trait DescribesTicketListTab
{
    #[Reactive]
    public ?string $activeTab = null;

    /**
     * @return Builder<Ticket>|null
     */
    protected function ticketsInActiveTab(): ?Builder
    {
        if ($this->activeTab === null) {
            return null;
        }

        /** @var ListTickets $page */
        $page = app('livewire')->new(TicketResource::getPages()['index']->getPage());

        return $page->ticketsInTab($this->activeTab);
    }

    protected function isOnEscalatedTab(): bool
    {
        return in_array($this->activeTab, ['linked', 'my_linked'], true);
    }
}
