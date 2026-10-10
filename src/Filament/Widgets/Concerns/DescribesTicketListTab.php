<?php

namespace Padmission\Tickets\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;

/*
 * List cards use Filament's filtered page query, built on the package's own
 * list page so a host page's view and column preferences are never touched.
 * Without a page tab, widgets keep their panel-wide dashboard metrics.
 */
trait DescribesTicketListTab
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListTickets::class;
    }

    #[On('refresh-ticket-stats')]
    public function refreshStats(): void {}

    /**
     * @return Builder<Ticket>|null
     */
    protected function ticketsInActiveTab(): ?Builder
    {
        if ($this->activeTab === null) {
            return null;
        }

        /** @var Builder<Ticket> $query */
        $query = $this->getPageTableQuery();

        return $query->reorder();
    }

    protected function isOnDirectQuestions(): bool
    {
        return (bool) data_get($this->tableFilters, 'direct_questions.isActive');
    }
}
