<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketNotification;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class CustomTicket extends Ticket {}

class CustomTicketActivity extends TicketActivity {}

class CustomTicketDisposition extends TicketDisposition {}

class CustomTicketStatus extends TicketStatus {}

class CustomTicketPriority extends TicketPriority {}

class CustomTicketNotification extends TicketNotification {}

it('resolves model classes', function () {
    expect(TicketPlugin::resolveModelClass(Ticket::class))->toBe(Ticket::class);
});

it('returns replaced classes', function () {
    config()->set('padmission-tickets.models.'.Ticket::class, 'NewModelClass');

    expect(TicketPlugin::resolveModelClass(Ticket::class))->toBe('NewModelClass');
});

it('ensures model resolution works with custom models', function (string $given, string $resolved) {
    config()->set('padmission-tickets.models', [$given => $resolved]);

    expect(TicketPlugin::resolveModelClass($given))->toBe($resolved);
})->with([
    [Ticket::class, CustomTicket::class],
    [TicketActivity::class, CustomTicketActivity::class],
    [TicketDisposition::class, CustomTicketDisposition::class],
    [TicketStatus::class, CustomTicketStatus::class],
    [TicketPriority::class, CustomTicketPriority::class],
    [TicketNotification::class, CustomTicketNotification::class],
]);

// Linked Tickets Tests
it('allows linked tickets configuration', function () {
    $plugin = TicketPlugin::make();

    expect($plugin->hasLinkedTickets())->toBeFalse();

    $plugin->allowLinkedTickets();
    expect($plugin->hasLinkedTickets())->toBeTrue();

    $plugin->allowLinkedTickets(false);
    expect($plugin->hasLinkedTickets())->toBeFalse();
});

it('returns all panels for linked ticket creation by default', function () {
    $adminPanel = mock(Panel::class);
    $adminPanel->shouldReceive('getId')->andReturn('admin');

    $supportPanel = mock(Panel::class);
    $supportPanel->shouldReceive('getId')->andReturn('support');

    $allPanels = [
        'admin' => $adminPanel,
        'support' => $supportPanel,
    ];

    Filament::shouldReceive('getPanels')->andReturn($allPanels);

    $plugin = TicketPlugin::make()->allowLinkedTickets();

    $panels = $plugin->getPanelsForLinkedTicketCreation();

    expect($panels)
        ->toBeArray()
        ->toHaveCount(2)
        ->toBe($allPanels);
});

it('returns empty array when linked tickets disabled', function () {
    $plugin = TicketPlugin::make()->allowLinkedTickets(false);

    $panels = $plugin->getPanelsForLinkedTicketCreation();

    expect($panels)->toBe([]);
});

it('filters panels when only specific panels allowed', function () {
    $adminPanel = mock(Panel::class);
    $adminPanel->shouldReceive('getId')->andReturn('admin');

    $supportPanel = mock(Panel::class);
    $supportPanel->shouldReceive('getId')->andReturn('support');

    $customerPanel = mock(Panel::class);
    $customerPanel->shouldReceive('getId')->andReturn('customer');

    $allPanels = [
        'admin' => $adminPanel,
        'support' => $supportPanel,
        'customer' => $customerPanel,
    ];

    Filament::shouldReceive('getPanels')->andReturn($allPanels);

    $plugin = TicketPlugin::make()
        ->allowLinkedTickets(true, ['admin', 'support']);

    $allowedPanels = $plugin->getPanelsForLinkedTicketCreation();

    expect($allowedPanels)->toHaveCount(2);
    expect(array_keys($allowedPanels))->toEqual(['admin', 'support']);
});

it('returns all panels when only parameter is null', function () {
    $adminPanel = $this->mock(Panel::class);
    $adminPanel->shouldReceive('getId')->andReturn('admin');

    $allPanels = ['admin' => $adminPanel];

    Filament::shouldReceive('getPanels')
        ->andReturn($allPanels);

    $plugin = TicketPlugin::make()
        ->allowLinkedTickets();

    $panels = $plugin->getPanelsForLinkedTicketCreation();

    expect($panels)->toBe($allPanels);
});
