<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\RemoveFromEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ViewOriginalConversationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

function escalationAction(string $action): TestAction
{
    return TestAction::make($action)->schemaComponent('escalationActions', schema: 'form');
}

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->user = $this->login(User::factory()->create(['name' => 'Tess Support']));
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');
});

describe('Adding to an existing escalation', function () {
    it('offers both ways to escalate, with help naming the team', function () {
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible(escalationAction(CreateLinkedTicketAction::class))
            ->assertActionVisible(escalationAction(AddToEscalationAction::class))
            ->assertSee(__('padmission-tickets::tickets.actions.add_to_escalation.help_to', ['team' => 'Platform Support']));
    });

    it('offers only open escalations', function () {
        $ticket = Ticket::factory()->open()->create();
        $open = Ticket::factory()->open()->create(['panel' => 'test2']);
        $closed = Ticket::factory()->closed()->create(['panel' => 'test2']);

        expect(LinkedTicketCandidates::openEscalations(Ticket::query(), $ticket)->pluck('id'))
            ->toContain($open->id)
            ->not->toContain($closed->id);
    });

    it('adds the ticket and records it on both tickets', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction(escalationAction(AddToEscalationAction::class), ['escalation' => $escalation->id])
            ->assertHasNoActionErrors();

        expect($ticket->refresh()->linked_ticket_id)->toBe($escalation->id)
            ->and($ticket->ticketActivities()->where('type', ActivityType::AddedToEscalation)->first()->content)
            ->toBe("Added to escalation #{$escalation->id} by Tess Support")
            ->and($escalation->ticketActivities()->where('type', ActivityType::OriginalAdded)->first()->content)
            ->toBe("Original #{$ticket->id} added by Tess Support");
    });

    it('says which escalation the ticket is part of and how many others share it', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        Ticket::factory()->count(2)->create(['linked_ticket_id' => $escalation->id]);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee("#{$escalation->id}")
            ->assertSee('with 2 other tickets')
            ->assertActionVisible(escalationAction(RemoveFromEscalationAction::class));
    });

    it('removes the ticket after confirmation and records it on both tickets', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction(escalationAction(RemoveFromEscalationAction::class));

        expect($ticket->refresh()->linked_ticket_id)->toBeNull()
            ->and($ticket->ticketActivities()->where('type', ActivityType::RemovedFromEscalation)->first()->content)
            ->toBe("Removed from escalation #{$escalation->id} by Tess Support")
            ->and($escalation->ticketActivities()->where('type', ActivityType::OriginalRemoved)->exists())->toBeTrue();
    });

    it('refuses to open a new escalation for a ticket already linked to one it cannot see', function () {
        $hidden = Ticket::factory()->open()->create(['panel' => 'test2']);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $hidden->id]);
        $ticket->setRelation('parentTicket', null);

        expect(CreateLinkedTicketAction::isAvailableFor($ticket))->toBeFalse()
            ->and(resolve(TicketEscalationLinks::class)->canOpenEscalation($ticket))->toBeFalse();
    });
});

describe('Originals on an escalated ticket', function () {
    beforeEach(function () {
        TicketPlugin::get()->allowLinkedTicketsTo([]);
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    });

    it('shows each original with its origin and requester', function () {
        TicketPlugin::get()->describeTicketOriginUsing(fn (Ticket $ticket): string => "Org of {$ticket->id}");

        $requester = User::factory()->create(['name' => 'Rita Requester']);
        $escalation = Ticket::factory()->open()->create();
        $original = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id, 'submitter_id' => $requester->id]);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee("Org of {$original->id}")
            ->assertSee('Requested by Rita Requester');
    });

    it('lets staff choose which original conversation to read', function () {
        $escalation = Ticket::factory()->open()->create();
        $first = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);
        $second = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);

        foreach ([$first, $second] as $index => $original) {
            TicketActivity::factory()->create([
                'ticket_id' => $original->id,
                'type' => ActivityType::Message,
                'sender' => ActivitySender::User,
                'content' => $index === 0 ? '<p>Message on the first original</p>' : '<p>Message on the second original</p>',
            ]);
        }

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->mountAction(ViewOriginalConversationAction::class)
            ->assertSchemaComponentVisible('original', 'mountedActionSchema0')
            ->assertMountedActionModalSee('Message on the first original')
            ->assertMountedActionModalDontSee('Message on the second original')
            ->assertMountedActionModalSee(["#{$first->id}", "#{$second->id}"]);
    });

    it('can clear an original the picker would no longer offer', function () {
        $escalation = Ticket::factory()->open()->create();
        $outOfRules = Ticket::factory()->create(['panel' => 'test3', 'linked_ticket_id' => $escalation->id]);

        expect(resolve(TicketEscalationLinks::class)->syncOriginals($escalation, []))->toBeTrue()
            ->and($outOfRules->refresh()->linked_ticket_id)->toBeNull()
            ->and($escalation->ticketActivities()->where('type', ActivityType::OriginalRemoved)->exists())->toBeTrue();
    });

    it('ignores repeated ids and still refuses an original linked elsewhere', function () {
        $escalation = Ticket::factory()->open()->create();
        $free = Ticket::factory()->create(['panel' => 'test2']);
        $elsewhere = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => Ticket::factory()->create()->id]);

        $links = resolve(TicketEscalationLinks::class);

        expect($links->syncOriginals($escalation, [$free->id, $free->id]))->toBeTrue()
            ->and($free->refresh()->linked_ticket_id)->toBe($escalation->id)
            ->and($links->syncOriginals($escalation, [$free->id, $elsewhere->id]))->toBeFalse()
            ->and($elsewhere->refresh()->linked_ticket_id)->not->toBe($escalation->id);
    });
});

describe('Escalated tabs in a cross-tenant panel', function () {
    beforeEach(function () {
        config()->set('padmission-tickets.tenancy.enabled', true);
        config()->set('padmission-tickets.models', [
            Authenticatable::class => User::class,
            Ticket::class => CustomTicket::class,
        ]);
        Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());

        CustomTicket::addGlobalScope('viewer-tenant', fn ($query) => $query->where('tickets.tenant_id', 1));

        TicketPlugin::get()
            ->customizeTicketQuery(fn ($query) => $query->withoutGlobalScope('viewer-tenant'))
            ->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('viewer-tenant'));
    });

    afterEach(fn () => CustomTicket::clearBootedModels());

    it('lists escalations whose originals belong to another tenant', function () {
        $escalation = CustomTicket::factory()->open()->create(['panel' => 'test2', 'tenant_id' => 2]);
        CustomTicket::factory()->create(['tenant_id' => 2, 'linked_ticket_id' => $escalation->id]);

        $tab = Livewire::test(ListTickets::class)->instance()->getTabs()['linked'];

        expect($tab->modifyQuery(CustomTicket::query()->withoutGlobalScope('viewer-tenant'))->pluck('id'))
            ->toContain($escalation->id);
    });
});
