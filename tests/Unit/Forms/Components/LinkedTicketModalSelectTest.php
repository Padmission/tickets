<?php

use Filament\Forms\Components\ModalTableSelect;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

use function Pest\Laravel\partialMock;

beforeEach(function () {
    $this->login();
});

it('extends ModalTableSelect', function () {
    expect(LinkedTicketModalSelect::make('test'))
        ->toBeInstanceOf(ModalTableSelect::class);
});

it('has correct placeholder for single selection', function () {
    $placeholder = LinkedTicketModalSelect::make('test')->getPlaceholder();

    expect($placeholder)->toBe('Not linked');
});

it('has correct placeholder for multiple selection', function () {
    $placeholder = LinkedTicketModalSelect::make('test')
        ->multiple()
        ->getPlaceholder();

    expect($placeholder)->toBe('No tickets linked');
});

it('renders ticket badge with ID', function () {
    $ticket = Ticket::factory()->create([
        'panel' => 'test',
        'subject' => 'Test Ticket Subject',
    ]);

    $html = LinkedTicketModalSelect::make('linked_tickets')
        ->configure()
        ->getOptionLabelFromRecord($ticket);

    expect($html->toHtml())
        ->toContain("#{$ticket->id}")
        ->toContain('Test Ticket Subject');
});

it('renders ticket link when user can access panel', function () {
    $ticket = Ticket::factory()->create([
        'panel' => 'test',
        'subject' => 'Test Ticket Subject',
    ]);

    $html = LinkedTicketModalSelect::make('linked_tickets')
        ->configure()
        ->getOptionLabelFromRecord($ticket);

    expect($html->toHtml())
        ->toContain('Test Ticket Subject')
        ->toContain('href=')
        ->toContain('fi-icon fi-size-sm'); // Icon for external link
});

it('does not render ticket link when user cannot access panel', function () {

    $ticket = Ticket::factory()->create([
        'panel' => 'test',
        'subject' => 'Test Ticket Subject',
    ]);

    $mockedUser = partialMock(User::class)
        ->shouldReceive('can')
        ->withArgs(['view', $ticket])
        ->andReturn(false)
        ->getMock();

    $this->actingAs($mockedUser);

    $html = LinkedTicketModalSelect::make('linked_tickets')
        ->configure()
        ->getOptionLabelFromRecord($ticket);

    expect($html->toHtml())
        ->toContain('Test Ticket Subject')
        ->not->toContain('href=');
});
