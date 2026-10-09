<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseAsDuplicateAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;

/*
 * Each panel keeps its own statuses, priorities and dispositions, and one
 * organization's are named alike in each. A ticket's relation reaches every
 * panel's rows so the ticket can show the one that opened it; a list of
 * choices must not, or each name is offered once per panel.
 */
beforeEach(function () {
    $this->me = $this->login();

    foreach (['test', 'test2'] as $panel) {
        $this->status[$panel] = [
            'open' => TicketStatus::factory()->create(['panel' => $panel, 'order' => 1, 'display_name' => 'Open']),
            'closed' => TicketStatus::factory()->create(['panel' => $panel, 'order' => 2, 'display_name' => 'Closed']),
        ];
        $this->priority[$panel] = TicketPriority::factory()->create(['panel' => $panel, 'order' => 1, 'display_name' => 'Normal']);
        $this->disposition[$panel] = TicketDisposition::factory()->create(['panel' => $panel, 'display_name' => 'Resolved']);
    }

    $this->ticket = Ticket::factory()->create([
        'panel' => 'test',
        'status_id' => $this->status['test']['open']->id,
        'priority_id' => $this->priority['test']->id,
        'disposition_id' => null,
    ]);
});

it('offers the list filters only this panel\'s statuses and priorities', function () {
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');

    expect($page->instance()->getTable()->getFilter('status')->getOptions())
        ->toBe([$this->status['test']['open']->id => 'Open', $this->status['test']['closed']->id => 'Closed'])
        ->and($page->instance()->getTable()->getFilter('priority')->getOptions())
        ->toBe([$this->priority['test']->id => 'Normal']);
});

it('offers the edit dialog only this panel\'s statuses and priorities', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->mountAction(EditTicketAction::class)
        ->assertFormFieldExists('status_id', function ($field): bool {
            expect(array_keys($field->getOptions()))
                ->toBe([$this->status['test']['open']->id, $this->status['test']['closed']->id]);

            return true;
        })
        ->assertFormFieldExists('priority_id', function ($field): bool {
            expect(array_keys($field->getOptions()))->toBe([$this->priority['test']->id]);

            return true;
        });
});

it('offers the close dialogs only this panel\'s dispositions', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->mountAction(CloseTicketAction::class)
        ->assertFormFieldExists('disposition', function ($field): bool {
            expect(panelsOf(array_keys($field->getOptions())))->toBe(['test']);

            return true;
        });

    Ticket::factory()->create([
        'panel' => 'test',
        'status_id' => $this->status['test']['open']->id,
        'priority_id' => $this->priority['test']->id,
    ]);

    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->mountAction(CloseAsDuplicateAction::class)
        ->assertFormFieldExists('disposition', function ($field): bool {
            expect(panelsOf(array_keys($field->getOptions())))->toBe(['test']);

            return true;
        });
});

it('offers a bulk close only this panel\'s disposition names', function () {
    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->selectTableRecords([$this->ticket])
        ->mountAction(TestAction::make('close-tickets')->table()->bulk())
        ->assertFormFieldExists('disposition', function ($field): bool {
            expect($field->getOptions())->toBe(['Resolved' => 'Resolved']);

            return true;
        });
});

it('closes and reopens onto this panel\'s own statuses', function () {
    $this->ticket->close();

    expect($this->ticket->refresh()->status_id)->toBe($this->status['test']['closed']->id);

    $this->ticket->reopen();

    expect($this->ticket->refresh()->status_id)->toBe($this->status['test']['open']->id);
});

/**
 * @param  list<int|string>  $ids
 * @return list<string>
 */
function panelsOf(array $ids): array
{
    return TicketDisposition::withoutGlobalScopes()->whereKey($ids)->pluck('panel')->unique()->values()->all();
}
