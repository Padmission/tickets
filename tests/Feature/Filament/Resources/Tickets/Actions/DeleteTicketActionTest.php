<?php

use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\DeleteTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
});

function moreMenu(array $actions): ActionGroup
{
    return collect($actions)->sole(fn (mixed $action): bool => $action instanceof ActionGroup);
}

function menuNames(ActionGroup $menu): array
{
    return collect($menu->getActions())->map(fn ($action): string => $action->getName())->values()->all();
}

it('keeps View on the row and puts Reassign and Delete in its unlabelled ⋯ menu', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create();

    $component = Livewire::test(ListTickets::class)
        ->assertActionVisible(TestAction::make('view')->table($ticket))
        ->assertActionVisible(TestAction::make('reassign-ticket')->table($ticket))
        ->assertActionVisible(TestAction::make('delete-ticket')->table($ticket));

    $menu = moreMenu($component->instance()->getTable()->getRecordActions());

    expect(menuNames($menu))->toBe(['reassign-ticket', 'delete-ticket'])
        ->and($menu->isIconButton())->toBeTrue();
});

it('puts Delete alone in the ticket page\'s unlabelled ⋯ menu, beside the actions it keeps in view', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create();

    $page = Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible('close-ticket')
        ->assertActionVisible(DeleteTicketAction::class);

    $menu = moreMenu(invade($page->instance())->getHeaderActions());

    expect(menuNames($menu))->toBe(['delete-ticket'])
        ->and($menu->isIconButton())->toBeTrue();
});

it('deletes a ticket from its page, softly, after asking, and goes back to the list', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(DeleteTicketAction::class)
        ->assertMountedActionModalSee([
            'Delete this ticket?',
            'It is removed from every list with its conversation, and the requester can no longer open it.',
            'Delete ticket',
        ])
        ->callMountedAction()
        ->assertNotified('Ticket deleted')
        ->assertRedirect(TicketResource::getUrl('index'));

    expect(Ticket::query()->find($ticket->id))->toBeNull()
        ->and(Ticket::withTrashed()->find($ticket->id)->trashed())->toBeTrue();
});

it('deletes a ticket from its row', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make(DeleteTicketAction::class)->table($ticket))
        ->assertNotified('Ticket deleted');

    expect(Ticket::withTrashed()->find($ticket->id)->trashed())->toBeTrue();
});

it('offers no way to restore or force delete a ticket, and no filter for deleted ones', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create();

    $table = Livewire::test(ListTickets::class)
        ->assertTableBulkActionExists('delete')
        ->instance()->getTable();

    expect(array_keys($table->getFilters()))->not->toContain('trashed')
        ->and(collect($table->getFlatActions())->keys()->intersect(['restore', 'forceDelete', 'force-delete']))->toBeEmpty();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionDoesNotExist('restore')
        ->assertActionDoesNotExist('forceDelete');
});

it('lets a supporter delete a ticket living in the panel they work in, and nobody else', function (Closure $ticket, string $panel, bool $asSubmitter, bool $expected) {
    Gate::policy(Ticket::class, TicketPolicy::class);
    $supporter = User::factory()->create();
    Filament::setCurrentPanel($panel);
    $record = $ticket();

    if ($asSubmitter) {
        $record->update(['submitter_id' => $supporter->id]);
    }

    expect(Gate::forUser($supporter)->allows('delete', $record->refresh()))->toBe($expected);
})->with([
    'an original, in its panel' => [fn (): Ticket => Ticket::factory()->open()->create(), 'test', false, true],
    'their own ticket, as its requester' => [fn (): Ticket => Ticket::factory()->open()->create(), 'test', true, false],
    'an original, from the panel it was escalated to' => [fn (): Ticket => Ticket::factory()->open()->create(), 'test2', false, false],
    'an escalation, from the panel that escalated it' => [fn (): Ticket => escalationFrom(), 'test', false, false],
    'an escalation, in its panel' => [fn (): Ticket => escalationFrom(), 'test2', false, true],
]);

it('says what deleting an escalation leaves behind', function () {
    $this->login();
    $escalation = escalationFrom(attributes: ['submitter_id' => User::factory()->create()->id]);
    Filament::setCurrentPanel('test2');

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->mountAction(DeleteTicketAction::class)
        ->assertMountedActionModalSee('It is removed from every list with its conversation, and the team that escalated it can no longer open it. The original tickets stay open and can be escalated again.');
});
