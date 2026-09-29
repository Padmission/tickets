<?php

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->login();
    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
});

function greetAction(bool $authorized = true): Closure
{
    return fn (Model $person, Ticket $ticket): array => [
        Action::make('greet')
            ->label("Greet {$person->name} on #{$ticket->id}")
            ->authorize($authorized)
            ->action(fn () => test()->greeted = [$person->getKey(), $ticket->getKey()]),
    ];
}

it('offers the host\'s actions beside the person who asked, for that person and ticket', function () {
    TicketPlugin::get()->personActionsUsing(greetAction());
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee("Greet Aisha Brooks on #{$ticket->id}")
        ->callAction(TestAction::make('greet')->schemaComponent('submitter', schema: 'form'));

    expect($this->greeted)->toBe([$this->requester->id, $ticket->id]);
});

it('offers nothing beside the person when the host returns no action, or the viewer may not use it', function (?Closure $actions) {
    TicketPlugin::get()->personActionsUsing($actions);
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee('Greet Aisha Brooks');
})->with([
    'no hook' => [null],
    'an empty list' => [fn () => fn (): array => []],
    'an action the viewer may not use' => [fn () => greetAction(authorized: false)],
]);

it('offers them beside the requester in the original shown next to an escalation, and beside its contact', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->personActionsUsing(greetAction());
    TicketPlugin::get('test2')->linkedConversationView(TicketPlugin::LINKED_VIEW_BESIDE);
    $escalation = escalationFrom(attributes: ['submitter_id' => User::factory()->create(['name' => 'Test Admin'])->id]);
    $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id, 'submitter_id' => $this->requester->id]);
    Filament::setCurrentPanel('test2');

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->call('showLinked', $original->id)
        ->assertSee("Greet Aisha Brooks on #{$original->id}")
        ->call('closeLinked')
        ->assertDontSee('Greet Aisha Brooks')
        ->assertSee("Greet Test Admin on #{$escalation->id}");
});

it('draws each one icon-only right after the label, named by its tooltip and for screen readers', function () {
    TicketPlugin::get()->personActionsUsing(fn (Model $person, Ticket $ticket): array => [
        Action::make('impersonate')->label('Impersonate')->icon('heroicon-o-user-circle')->action(fn () => null),
        Action::make('greet')->label('Greet')->action(fn () => null),
    ]);
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionExists(TestAction::make('impersonate')->schemaComponent('submitter', schema: 'form'), fn (Action $action): bool => $action->isIconButton()
            && $action->getIcon() === 'heroicon-o-user-circle'
            && $action->getTooltip() === 'Impersonate')
        // An action the host gave no icon gets one, rather than an empty button.
        ->assertActionExists(TestAction::make('greet')->schemaComponent('submitter', schema: 'form'), fn (Action $action): bool => $action->isIconButton()
            && $action->getIcon() === Heroicon::OutlinedArrowRightEndOnRectangle
            && $action->getTooltip() === 'Greet')
        ->assertSeeHtml('aria-label="Impersonate"')
        ->assertSeeHtml('pad-ti-edit-entry');
});

it('draws them icon-only right after the requester\'s name in the original shown next to an escalation', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->personActionsUsing(fn (Model $person, Ticket $ticket): array => [
        Action::make('impersonate')->label('Impersonate')->icon('heroicon-o-user-circle')->action(fn () => null),
    ]);
    TicketPlugin::get('test2')->linkedConversationView(TicketPlugin::LINKED_VIEW_BESIDE);
    $escalation = escalationFrom();
    $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id, 'submitter_id' => $this->requester->id]);
    Filament::setCurrentPanel('test2');

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->call('showLinked', $original->id)
        ->assertActionExists(TestAction::make('impersonate')->schemaComponent('linkedRequesterActions', schema: 'form'), fn (Action $action): bool => $action->isIconButton()
            && $action->getTooltip() === 'Impersonate')
        ->assertSeeHtmlInOrder(['Requested by', 'Aisha Brooks', 'pad-ti-transcript__person-actions', 'aria-label="Impersonate"', 'Assigned to']);
});
