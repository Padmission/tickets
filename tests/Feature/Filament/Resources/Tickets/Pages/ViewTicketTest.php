<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
});

it('displays ticket subject as page heading', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Test Ticket Subject']);

    $component = Livewire::test(ViewTicket::class, ['record' => $ticket->id]);

    $heading = $component->instance()->getHeading();

    expect($heading)->toBeInstanceOf(\Illuminate\Support\HtmlString::class);
    expect((string) $heading)->toBe('Test Ticket Subject');
});

it('has chat section in main content area', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('pad-ti-chat-section');
});

it('shows ticket status and priority', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee($ticket->status->display_name)
        ->assertSee($ticket->priority->display_name);
});

describe('Linked Tickets', function () {
    it('shows CreateLinkedTicketAction when can create linked tickets', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);

        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible(CreateLinkedTicketAction::class);
    });

    it('hides CreateLinkedTicketAction when cannot create linked tickets', function () {
        TicketPlugin::get()->allowLinkedTicketsTo([]);

        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden(CreateLinkedTicketAction::class);
    });

    it('hides linked tickets section when feature disabled', function () {
        TicketPlugin::get()->allowLinkedTicketsTo([]);

        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
    });

    it('shows linked tickets section when can create linked tickets', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);

        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
    });

    it('shows linked tickets section when other panel links to this', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);

        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
    });

    it('does show parent ticket select if it can link to another panel', function () {
        // Setup
        $plugin = TicketPlugin::get()->allowLinkedTicketsTo(['test']);
        $mockedPlugin = mock($plugin)
            ->shouldReceive('getPanelsForLinkedTicketCreation')
            ->andReturn(['panel' => Panel::make()->id('panel2')])
            ->getMock();

        Filament::setCurrentPanel('test');
        Filament::getCurrentPanel()->plugin($mockedPlugin);

        $ticket = Ticket::factory()->create();

        // Assert
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.parent_ticket'));
    });

    it('does not show parent ticket select if it cannot link to another panel', function () {
        // Setup
        $plugin = TicketPlugin::get()->allowLinkedTicketsTo([]);
        $mockedPlugin = mock($plugin)
            ->shouldReceive('getLinkedTicketChildPanels')
            ->andReturn(['test'])
            ->getMock()
            ->shouldReceive('hasLinkedTickets')
            ->andReturn(true)
            ->getMock();

        Filament::setCurrentPanel('test');
        Filament::getCurrentPanel()->plugin($mockedPlugin);

        $ticket = Ticket::factory()->create();

        // Assert
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.parent_ticket'));
    });

    it('does show child tickets select if panels link to the current panel', function () {
        $plugin = TicketPlugin::make()->allowLinkedTicketsTo();
        $mockedPlugin = mock($plugin)
            ->shouldReceive('hasLinkedTickets')
            ->andReturn(true)
            ->getMock()
            ->shouldReceive('getLinkedTicketChildPanels')
            ->andReturn(['panel1' => Panel::make()])
            ->getMock();

        Filament::setCurrentPanel('test');
        Filament::getCurrentPanel()->plugin($mockedPlugin);

        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertFormFieldVisible('childTickets');
    });

    it('does not show child tickets select if no panels link to the current panel', function () {
        $plugin = TicketPlugin::make()->allowLinkedTicketsTo();
        $mockedPlugin = mock($plugin)
            ->shouldReceive('hasLinkedTickets')
            ->andReturn(true)
            ->getMock()
            ->shouldReceive('getLinkedTicketChildPanels')
            ->andReturn([])
            ->getMock();

        Filament::setCurrentPanel('test');
        Filament::getCurrentPanel()->plugin($mockedPlugin);

        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertFormFieldHidden('childTickets');
    });

    it('updates parent ticket relationship via form', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);

        $parentTicket = Ticket::factory()->create();
        $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertFormFieldVisible('parentTicket')
            ->fillForm(['parentTicket' => $parentTicket->id]);

        expect($ticket->refresh()->linked_ticket_id)->toBe($parentTicket->id);
    });

    it('updates child linked tickets relationship via form', function () {
        $plugin = TicketPlugin::make()->allowLinkedTicketsTo();

        $mockedPlugin = mock($plugin)
            ->shouldReceive('hasLinkedTickets')
            ->andReturn(true)
            ->getMock()
            ->shouldReceive('getLinkedTicketChildPanels')
            ->andReturn(['panel1' => Panel::make()])
            ->getMock();

        Filament::setCurrentPanel('test');
        Filament::getCurrentPanel()->plugin($mockedPlugin);

        $parentTicket = Ticket::factory()->create();
        $childTicket1 = Ticket::factory()->create(['linked_ticket_id' => null]);
        $childTicket2 = Ticket::factory()->create(['linked_ticket_id' => null]);

        Livewire::test(ViewTicket::class, ['record' => $parentTicket->id])
            ->assertFormFieldVisible('childTickets')
            ->fillForm(['childTickets' => [$childTicket1->id, $childTicket2->id]]);

        expect($childTicket1->refresh()->linked_ticket_id)->toBe($parentTicket->id);
        expect($childTicket2->refresh()->linked_ticket_id)->toBe($parentTicket->id);
    });

    it('removes tickets from linked relationship when deselected', function () {
        TicketPlugin::get()->allowLinkedTicketsTo();

        $parentTicket = Ticket::factory()->create();
        $childTicket1 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);
        $childTicket2 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);

        // @TODO: Report bug to Filament. `InteractsWithSchema::fillFormDataForTesting()`
        // does not have correct state in afterStateUpdated() hook
        Livewire::test(ViewTicket::class, ['record' => $parentTicket->id])
            ->fillForm(['childTickets' => [$childTicket1->id]]);

        expect($childTicket1->refresh()->linked_ticket_id)->toBe($parentTicket->id);
        expect($childTicket2->refresh()->linked_ticket_id)->toBeNull();
    })->skip('Filament Testing Bug');

    it('clears all child tickets when form field is emptied', function () {
        TicketPlugin::get()->allowLinkedTicketsTo();

        $parentTicket = Ticket::factory()->create();
        $childTicket1 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);
        $childTicket2 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);

        Livewire::test(ViewTicket::class, ['record' => $parentTicket->id])
            ->fillForm(['childTickets' => []]);

        expect($childTicket1->refresh()->linked_ticket_id)->toBeNull();
        expect($childTicket2->refresh()->linked_ticket_id)->toBeNull();
    })->skip('Filament Testing Bug: Property [$data.linkedTickets] not found on component');

    it('restricts parent ticket options to only tickets from parent panels', function () {
        Filament::getCurrentPanel()->plugin(
            TicketPlugin::make()->allowLinkedTicketsTo(['test2', 'test3'])
        );

        (new TicketStatusSeeder)->run();

        $ticket = Ticket::factory()->create();

        Ticket::factory()->create(['panel' => 'test1']);
        Ticket::factory()->create(['panel' => 'test2']);
        Ticket::factory()->create(['panel' => 'test3']);

        $selectAction = TestAction::make('select')->schemaComponent('parentTicket');

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.parent_ticket'))
            ->mountAction($selectAction)
            ->assertActionMounted($selectAction)
            ->assertMountedActionModalSee([
                'fi-ta-header-cell-panel',
                'Test2',
                'Test3',
            ]);
        // @TODO: Rename test panel so this can be tested properly
        // ->assertMountedActionModalDontSee(['Test']);
    });

    it('does not show panel column in ParentTicketTable when linking to a single panel', function () {
        Filament::getCurrentPanel()->plugin(
            TicketPlugin::make()->allowLinkedTicketsTo(['test2'])
        );

        (new TicketStatusSeeder)->run();

        $ticket = Ticket::factory()->create();
        $selectAction = TestAction::make('select')->schemaComponent('parentTicket');

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.parent_ticket'))
            ->mountAction($selectAction)
            ->assertActionMounted($selectAction)
            ->assertMountedActionModalDontSee([
                'fi-ta-header-cell-panel',
            ]);
    });

    it('restricts child ticket options to only tickets from child panels', function () {
        $plugin = TicketPlugin::make()
            ->allowLinkedTicketsTo(['test'])
            ->registerResources();

        Filament::getPanel('test2')->plugin($plugin);
        Filament::getPanel('test3')->plugin($plugin);

        (new TicketStatusSeeder)->run();

        $ticket = Ticket::factory()->create();

        Ticket::factory()->create(['panel' => 'test1']);
        Ticket::factory()->create(['panel' => 'test2']);
        Ticket::factory()->create(['panel' => 'test3']);

        $selectAction = TestAction::make('select')->schemaComponent('childTickets');

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.child_tickets'))
            ->mountAction($selectAction)
            ->assertActionMounted($selectAction)
            ->assertMountedActionModalSee([
                __('padmission-tickets::tickets.resources.tickets.panel'),
                'Test2',
                'Test3',
            ]);
        // @TODO: Rename test panel so this can be tested properly
        // ->assertMountedActionModalDontSee(['Test']);
    });

    it('does not show panel column in ChildTicketTable when tickets are from a single panel', function () {
        $plugin = TicketPlugin::make()
            ->allowLinkedTicketsTo(['test'])
            ->registerResources();

        Filament::getPanel('test2')->plugin($plugin);

        (new TicketStatusSeeder)->run();

        $ticket = Ticket::factory()->create();
        $selectAction = TestAction::make('select')->schemaComponent('childTickets');

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.child_tickets'))
            ->mountAction($selectAction)
            ->assertActionMounted($selectAction)
            ->assertMountedActionModalDontSee([
                'fi-ta-header-cell-panel',
            ]);
    });
});
