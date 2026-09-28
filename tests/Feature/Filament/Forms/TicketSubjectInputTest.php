<?php

use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

// As Journey and Connect configure every TextInput: markup-like text is refused, and tags are stripped when saved.
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();
    $this->login();

    TextInput::configureUsing(fn (TextInput $field): TextInput => $field
        ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strip_tags($state))
        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (preg_match('/<script|<iframe|on\w+\s*=/i', (string) $value)) {
                $fail('The :attribute contains invalid characters or code.');
            }
        }]));

    $this->subject = 'Error: <script>x</script> onclick=alert(1) when rent < 200';
});

it('escalates a ticket from before the rule whose subject looks like markup, keeping its subject', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $ticket = Ticket::factory()->open()->create(['subject' => $this->subject, 'linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), [
            'subject' => $this->subject,
            'message' => '<p>Please check.</p>',
        ])
        ->assertHasNoFormErrors();

    expect(Ticket::query()->withoutGlobalScopes()->whereKey($ticket->refresh()->linked_ticket_id)->value('subject'))->toBe($this->subject);
});

it('edits a ticket from before the rule whose subject looks like markup, keeping its subject', function () {
    $ticket = Ticket::factory()->open()->create(['subject' => $this->subject]);
    $priority = TicketPriority::query()->whereKeyNot($ticket->priority_id)->value('id');

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(EditTicketAction::class, [
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
            'priority_id' => $priority,
        ])
        ->assertHasNoActionErrors();

    expect($ticket->refresh())
        ->priority_id->toBe($priority)
        ->subject->toBe($this->subject);
});

it('refuses an escalation subject changed to carry markup', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $ticket = Ticket::factory()->open()->create(['subject' => 'Rent is wrong', 'linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), [
            'subject' => 'Rent <script>alert(1)</script>',
            'message' => '<p>Please check.</p>',
        ])
        ->assertHasFormErrors(['subject']);

    expect($ticket->refresh()->linked_ticket_id)->toBeNull();
});

it('keeps an escalation subject typed with a less-than sign that starts no tag, as typed', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $ticket = Ticket::factory()->open()->create(['subject' => 'Rent is wrong', 'linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), [
            'subject' => 'Rent < 200 since March',
            'message' => '<p>Please check.</p>',
        ])
        ->assertHasNoFormErrors();

    expect(Ticket::query()->withoutGlobalScopes()->whereKey($ticket->refresh()->linked_ticket_id)->value('subject'))->toBe('Rent < 200 since March');
});

it('still applies the host rules to other text inputs', function () {
    expect(invade(TextInput::make('name'))->rules)->not->toBe([]);
});
