<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
});

it('displays ticket subject as page heading', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Test Ticket Subject']);

    $component = Livewire::test(ViewTicket::class, ['record' => $ticket->id]);

    $heading = $component->instance()->getHeading();

    // Text, which Filament escapes, never HTML: a subject is whatever someone typed.
    expect($heading)->toBe('Test Ticket Subject');
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
    beforeEach(fn () => (new TicketStatusSeeder)->run());

    it('shows CreateLinkedTicketAction when can create linked tickets', function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);

        $ticket = Ticket::factory()->open()->create();
        escalationFrom(attributes: ['panel' => 'test']);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'))
            ->assertActionVisible(TestAction::make(AddToEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'));
    });

    it('hides CreateLinkedTicketAction when cannot create linked tickets', function () {
        TicketPlugin::get()->allowLinkedTicketsTo([]);

        $ticket = Ticket::factory()->open()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertDontSee(__('padmission-tickets::tickets.actions.create_linked_ticket.label'))
            ->assertDontSee(__('padmission-tickets::tickets.actions.add_to_escalation.label'));
    });

    it('hides linked tickets section when feature disabled', function () {
        TicketPlugin::get()->allowLinkedTicketsTo([]);

        $ticket = Ticket::factory()->open()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
    });

    it('shows linked tickets section when can create linked tickets', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);

        $ticket = Ticket::factory()->open()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
    });

    it('shows linked tickets section when other panel links to this', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);

        $ticket = Ticket::factory()->open()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
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

        $ticket = Ticket::factory()->open()->create();

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

        $ticket = Ticket::factory()->open()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertFormFieldHidden('childTickets');
    });

    it('updates child linked tickets relationship via form', function () {
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

        $parentTicket = Ticket::factory()->open()->create();
        $childTicket1 = Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => null]);
        $childTicket2 = Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => null]);

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

    it('restricts child ticket options to only tickets from child panels', function () {
        $plugin = TicketPlugin::make()
            ->allowLinkedTicketsTo(['test2'])
            ->registerResources();

        Filament::getPanel('test')->plugin($plugin);
        Filament::getPanel('test3')->plugin($plugin);

        (new TicketStatusSeeder)->run();

        // Read by the panel the ticket belongs to: another panel's ticket that was never escalated has no Escalation box.
        Filament::setCurrentPanel('test2');
        $ticket = Ticket::factory()->create(['panel' => 'test2', 'submitter_id' => auth()->id()]);

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
                'Test',
                'Test3',
            ]);
        // @TODO: Rename test panel so this can be tested properly
        // ->assertMountedActionModalDontSee(['Test']);
    });

    it('does not show panel column in ChildTicketTable when tickets are from a single panel', function () {
        $plugin = TicketPlugin::make()
            ->allowLinkedTicketsTo(['test3'])
            ->registerResources();

        Filament::getPanel('test2')->plugin($plugin);

        (new TicketStatusSeeder)->run();

        Filament::setCurrentPanel('test3');
        $ticket = Ticket::factory()->create(['panel' => 'test3', 'submitter_id' => auth()->id()]);
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

describe('Linked ticket permissions', function () {
    it('does not let a user who cannot edit the ticket change its escalation', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test']);
        Gate::policy(Ticket::class, ReadOnlyTicketPolicy::class);

        $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

        $abilities = [];
        Event::listen(GateEvaluated::class, function (GateEvaluated $event) use (&$abilities) {
            $abilities[] = $event->ability;
        });

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertDontSee(__('padmission-tickets::tickets.actions.add_to_escalation.label'))
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));

        expect($abilities)->toContain('update')
            ->and($ticket->refresh()->linked_ticket_id)->toBeNull();
    });
});

class ReadOnlyTicketPolicy
{
    public function view(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return false;
    }
}

describe('Escalation explanation', function () {
    it('lets the two choices speak for a ticket that is not escalated yet', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
        (new TicketStatusSeeder)->run();

        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);
        escalationFrom();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.actions.add_to_escalation.help_to', ['team' => 'Platform Support']))
            ->assertDontSee('Not escalated');
    });

    it('explains that an escalated ticket stays with the team', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

        $escalated = Ticket::factory()->create(['panel' => 'test2']);
        $ticket = Ticket::factory()->create(['linked_ticket_id' => $escalated->id]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee('Escalated.')
            ->assertDontSee('View escalation');
    });

    it('explains where an escalated ticket came from, and who never sees it, by how many originals it has', function (array $requesters, string $sentence) {
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

        $ticket = Ticket::factory()->create();

        foreach ($requesters as $name) {
            Ticket::factory()->create([
                'panel' => 'test2',
                'linked_ticket_id' => $ticket->id,
                'submitter_id' => $name === null ? null : User::factory()->create(['name' => $name])->id,
                'submitter_data' => null,
            ]);
        }

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->call('closeLinked')
            ->assertSee("You're talking with the team that escalated this. {$sentence}");
    })->with([
        'one named original' => [['Aisha Brooks'], 'Aisha Brooks never sees this conversation.'],
        'one unnamed original' => [[null], 'The requester never sees this conversation.'],
        'originals from two people' => [['Aisha Brooks', 'Felix Moreno'], 'The requesters never see this conversation.'],
    ]);
});

describe('Composer', function () {
    it('offers keeping the ticket waiting only to the side that answers it', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

        $owner = User::factory()->create();
        $escalation = Ticket::factory()->create(['panel' => 'test2', 'submitter_id' => $owner->id]);
        Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

        $this->actingAs($owner);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSeeHtml('has-elevated-rights="false"')
            ->assertDontSeeHtml('has-elevated-rights="true"');

        Filament::setCurrentPanel('test2');
        $this->actingAs(User::factory()->create());

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSeeHtml('has-elevated-rights="true"');
    })->after(fn () => Filament::setCurrentPanel('test'));

    it('gives the reply box the default placeholder on the viewer\'s own ticket', function () {
        $ticket = Ticket::factory()->create(['submitter_id' => auth()->id()]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSeeHtml('placeholder="'.e(__('padmission-tickets::chat.chat.placeholder')).'"');
    });
});
