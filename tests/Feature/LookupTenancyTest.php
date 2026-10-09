<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseAsDuplicateAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Livewire\CopilotTicketPanel;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketCloser;
use Padmission\Tickets\Services\TicketDuplicates;
use Padmission\Tickets\Services\TicketStarter;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * Tenancy is left off on purpose. The ticket still carries an organization,
 * and a panel that serves several organizations has lifted the viewer scope,
 * which is what made every organization's rows show up in an assign picker.
 */
describe('Assigning a lookup on a multi-organization panel', function () {
    beforeEach(function () {
        foreach (['tickets', 'ticket_statuses', 'ticket_priorities', 'ticket_dispositions', 'users'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('tenant_id')->nullable());
        }

        config()->set('padmission-tickets.tenancy.enabled', false);

        // The other organization is inserted first, so an unscoped lookup
        // returns its row. Its closed status also sorts ahead.
        foreach ([2, 1] as $tenant) {
            $this->status[$tenant] = [
                'progress' => TicketStatus::factory()->create(['tenant_id' => $tenant, 'panel' => 'test', 'order' => $tenant === 2 ? 0 : 1, 'display_name' => 'In Progress']),
                'closed' => TicketStatus::factory()->create(['tenant_id' => $tenant, 'panel' => 'test', 'order' => $tenant === 2 ? 9 : 2, 'display_name' => 'Closed']),
            ];
            $this->priority[$tenant] = TicketPriority::factory()->create(['tenant_id' => $tenant, 'panel' => 'test', 'order' => $tenant === 2 ? 0 : 1, 'display_name' => 'Normal']);
            $this->disposition[$tenant] = [
                'resolved' => TicketDisposition::factory()->create(['tenant_id' => $tenant, 'panel' => 'test', 'display_name' => 'Resolved']),
            ];
        }

        $this->disposition[1]['duplicate'] = TicketDisposition::factory()->create(['tenant_id' => 1, 'panel' => 'test', 'display_name' => 'Duplicate']);
        $this->status[3] = [
            'progress' => TicketStatus::factory()->create(['tenant_id' => 3, 'panel' => 'test', 'order' => 1, 'display_name' => 'In Progress']),
            'closed' => TicketStatus::factory()->create(['tenant_id' => 3, 'panel' => 'test', 'order' => 2, 'display_name' => 'Closed']),
        ];
        $this->priority[3] = TicketPriority::factory()->create(['tenant_id' => 3, 'panel' => 'test', 'order' => 1, 'display_name' => 'Normal']);
        $this->disposition[3] = [
            'duplicate' => TicketDisposition::factory()->create(['tenant_id' => 3, 'panel' => 'test', 'display_name' => 'Duplicate']),
        ];

        TicketStatus::addGlobalScope('viewer-tenant', fn ($query) => $query->where('ticket_statuses.tenant_id', 2));
        TicketPriority::addGlobalScope('viewer-tenant', fn ($query) => $query->where('ticket_priorities.tenant_id', 2));
        TicketDisposition::addGlobalScope('viewer-tenant', fn ($query) => $query->where('ticket_dispositions.tenant_id', 2));

        foreach (['test', 'test2'] as $panel) {
            TicketPlugin::get($panel)->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('viewer-tenant'));
        }

        $this->me = $this->login();
        $this->ticket = organizationTicket(1);
    });

    afterEach(function () {
        TicketStatus::clearBootedModels();
        TicketPriority::clearBootedModels();
        TicketDisposition::clearBootedModels();
    });

    it('offers only that ticket\'s statuses, priorities and dispositions', function () {
        TicketStatus::factory()->create(['tenant_id' => 1, 'panel' => 'test2', 'order' => 1, 'display_name' => 'In Progress']);
        TicketDisposition::factory()->create(['tenant_id' => 1, 'panel' => 'test2', 'display_name' => 'Resolved']);
        $statuses = sortedIds([$this->status[1]['progress']->id, $this->status[1]['closed']->id]);
        $priorities = [$this->priority[1]->id];
        $dispositions = sortedIds([$this->disposition[1]['resolved']->id, $this->disposition[1]['duplicate']->id]);

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->mountAction(EditTicketAction::class)
            ->assertFormFieldExists('status_id', function ($field) use ($statuses): bool {
                expect(lookupTenancyOptionIds($field))->toBe($statuses);

                return true;
            })
            ->assertFormFieldExists('priority_id', function ($field) use ($priorities): bool {
                expect(lookupTenancyOptionIds($field))->toBe($priorities);

                return true;
            });

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->mountAction(CloseTicketAction::class)
            ->assertFormFieldExists('disposition', function ($field) use ($dispositions): bool {
                expect(lookupTenancyOptionIds($field))->toBe($dispositions);

                return true;
            });
    });

    it('refuses another organization\'s status or priority when it is posted', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, [
                'status_id' => $this->status[2]['closed']->id,
                'priority_id' => $this->priority[2]->id,
            ])
            ->assertHasActionErrors(['status_id', 'priority_id']);

        expect($this->ticket->refresh())
            ->status_id->toBe($this->status[1]['progress']->id)
            ->priority_id->toBe($this->priority[1]->id);
    });

    it('refuses another organization\'s disposition when it is posted, and closes with its own', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(CloseTicketAction::class, ['disposition' => $this->disposition[2]['resolved']->id])
            ->assertHasActionErrors(['disposition']);

        expect($this->ticket->refresh()->isClosed)->toBeFalse();

        expect(fn () => resolve(TicketCloser::class)->close($this->ticket, $this->disposition[2]['resolved']->id))
            ->toThrow(ValidationException::class);

        expect($this->ticket->refresh()->isClosed)->toBeFalse();

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(CloseTicketAction::class, ['disposition' => $this->disposition[1]['resolved']->id])
            ->assertHasNoActionErrors();

        expect($this->ticket->refresh())
            ->isClosed->toBeTrue()
            ->status_id->toBe($this->status[1]['closed']->id)
            ->disposition_id->toBe($this->disposition[1]['resolved']->id);
    });

    it('closes each selected ticket with its own organization\'s disposition and closed status', function () {
        $own = organizationTicket(1);
        $other = organizationTicket(2);

        Livewire::test(ListTickets::class, ['activeTab' => 'all'])
            ->selectTableRecords([$own, $other])
            ->callAction(TestAction::make('close-tickets')->table()->bulk(), ['disposition' => 'Resolved'])
            ->assertNotified('Closed 2 tickets.');

        expect($own->refresh())
            ->disposition_id->toBe($this->disposition[1]['resolved']->id)
            ->status_id->toBe($this->status[1]['closed']->id)
            ->and($other->refresh())
            ->disposition_id->toBe($this->disposition[2]['resolved']->id)
            ->status_id->toBe($this->status[2]['closed']->id);
    });

    it('skips a ticket whose organization has no disposition by that name, and says so', function () {
        $own = organizationTicket(1);
        $without = organizationTicket(3);

        Livewire::test(ListTickets::class, ['activeTab' => 'all'])
            ->selectTableRecords([$own, $without])
            ->callAction(TestAction::make('close-tickets')->table()->bulk(), ['disposition' => 'Resolved'])
            ->assertNotified('Closed 1 ticket. 1 was skipped: its organization has no disposition by that name.');

        expect($own->refresh())
            ->isClosed->toBeTrue()
            ->disposition_id->toBe($this->disposition[1]['resolved']->id)
            ->and($without->refresh())
            ->isClosed->toBeFalse()
            ->disposition_id->toBeNull();
    });

    it('offers each disposition name once when a bulk close spans organizations', function () {
        $own = organizationTicket(1);
        $other = organizationTicket(2);

        Livewire::test(ListTickets::class, ['activeTab' => 'all'])
            ->selectTableRecords([$own, $other])
            ->mountAction(TestAction::make('close-tickets')->table()->bulk())
            ->assertFormFieldExists('disposition', function ($field): bool {
                expect(array_keys($field->getOptions()))->toBe(['Duplicate', 'Resolved']);

                return true;
            });
    });

    it('offers only the ticket\'s own organization\'s dispositions when closing it as a duplicate', function () {
        $original = organizationTicket(1);
        $dispositions = sortedIds([$this->disposition[1]['resolved']->id, $this->disposition[1]['duplicate']->id]);

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->mountAction(CloseAsDuplicateAction::class)
            ->assertFormFieldExists('disposition', function ($field) use ($dispositions): bool {
                expect(lookupTenancyOptionIds($field))->toBe($dispositions);

                return true;
            })
            ->assertFormFieldExists('original', function ($field) use ($original): bool {
                expect(array_keys($field->getOptions()))->toBe([$original->id]);

                return true;
            });
    });

    it('keeps the duplicate candidates to the ticket\'s own organization', function () {
        $own = organizationTicket(1);
        organizationTicket(2);

        expect(resolve(TicketDuplicates::class)->candidates($this->ticket)->pluck('id')->all())
            ->toBe([$own->id]);
    });

    it('offers each supporter the host allows for that ticket, once', function () {
        $mine = User::factory()->create(['tenant_id' => 1, 'name' => 'Ana Reyes']);
        $theirs = User::factory()->create(['tenant_id' => 2, 'name' => 'Ana Reyes']);
        $colleague = User::factory()->create(['tenant_id' => 1, 'name' => 'Bo Lane']);

        TicketPlugin::get()->allSupportersQuery(fn (?Ticket $ticket = null) => User::query()
            ->when($ticket !== null, fn ($query) => $query->where('users.tenant_id', $ticket->getAttribute('tenant_id'))));

        $this->ticket->update(['assignee_id' => $mine->id]);

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->mountAction(TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form'))
            ->assertFormFieldExists('assignee_id', function ($field) use ($colleague, $theirs): bool {
                $ids = array_keys($field->getOptions());

                expect($ids)->toContain($colleague->id);
                expect(in_array($theirs->id, $ids, true))->toBeFalse();

                return true;
            });

        // Edit also keeps whoever the ticket is already assigned to, so the field
        // can show them, which Reassign has no reason to offer.
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->mountAction(EditTicketAction::class)
            ->assertFormFieldExists('assignee_id', function ($field) use ($mine, $theirs): bool {
                $ids = array_keys($field->getOptions());

                expect($ids)->toContain($mine->id)
                    ->toContain($this->ticket->assignee_id);
                expect(in_array($theirs->id, $ids, true))->toBeFalse();

                return true;
            });
    });

    it('lists an assignee filter option for each supporter rather than each organization', function () {
        $supporter = User::factory()->create(['tenant_id' => 1, 'name' => 'Ana Reyes']);
        organizationTicket(1, ['assignee_id' => $supporter->id]);

        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));

        $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');

        expect($page->instance()->getTable()->getFilter('assignee')->getRelationshipQuery()->pluck('name', 'id')->all())
            ->toBe([$supporter->id => 'Ana Reyes']);
    });

    /*
     * Two people of the same name in different organizations are two people, so
     * the filter keeps one option each: merging them would filter the wrong
     * tickets. The organization is not named here, which is worth knowing.
     */
    it('keeps a submitter option per person when two share a name', function () {
        $mine = User::factory()->create(['tenant_id' => 1, 'name' => 'Dana Whitaker']);
        $theirs = User::factory()->create(['tenant_id' => 2, 'name' => 'Dana Whitaker']);
        organizationTicket(1, ['submitter_id' => $mine->id]);
        organizationTicket(2, ['submitter_id' => $theirs->id]);

        $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
        $options = $page->instance()->getTable()->getFilter('submitter')->getRelationshipQuery()
            ->whereIn('id', [$mine->id, $theirs->id])->pluck('name', 'id')->all();

        expect($options)->toBe([$mine->id => 'Dana Whitaker', $theirs->id => 'Dana Whitaker']);
    });

    it('resolves a ticket onto its own organization\'s closed status', function () {
        $ticket = organizationTicket(1, ['submitter_id' => $this->me->id]);

        Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $ticket->id])
            ->call('resolveTicket');

        expect($ticket->refresh())
            ->isClosed->toBeTrue()
            ->status_id->toBe($this->status[1]['closed']->id);
    });

    it('opens a ticket on the requester\'s organization\'s status and priority', function () {
        $requester = User::factory()->create(['tenant_id' => 1]);

        $ticket = resolve(TicketStarter::class)->openFor($requester, 'Rent is wrong', '<p>Hello</p>');

        expect($ticket)
            ->status_id->toBe($this->status[1]['progress']->id)
            ->priority_id->toBe($this->priority[1]->id);
    });

    it('asks the other team with the asker\'s organization\'s status and priority', function () {
        $this->me->forceFill(['tenant_id' => 1])->save();
        $target = panelLookups('test2');

        $ticket = resolve(TicketStarter::class)->ask('test2', 'A question', '<p>Hello</p>');

        expect($ticket)
            ->panel->toBe('test2')
            ->status_id->toBe($target['status']->id)
            ->priority_id->toBe($target['priority']->id);
    });

    it('escalates with the original ticket\'s organization\'s status and priority', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        $target = panelLookups('test2');

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), [
                'subject' => 'Escalated rent',
                'message' => '<p>Please look</p>',
            ])
            ->assertHasNoFormErrors();

        $escalation = Ticket::query()->withoutGlobalScopes()->where('subject', 'Escalated rent')->sole();

        expect($escalation)
            ->panel->toBe('test2')
            ->status_id->toBe($target['status']->id)
            ->priority_id->toBe($target['priority']->id);
    });
});

it('creates an api ticket on the requester\'s organization\'s status and priority', function () {
    foreach (['tickets', 'ticket_statuses', 'ticket_priorities', 'users'] as $table) {
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('tenant_id')->nullable());
    }

    config()->set('padmission-tickets.tenancy.enabled', false);

    $own = panelLookups('test');
    $user = User::factory()->create(['tenant_id' => 1]);
    $this->actingAs($user);

    $id = $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Heat is out'])
        ->assertOk()
        ->json('id');

    expect(Ticket::query()->findOrFail($id))
        ->status_id->toBe($own['status']->id)
        ->priority_id->toBe($own['priority']->id);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function organizationTicket(int $tenant, array $attributes = []): Ticket
{
    return Ticket::factory()->create([
        'tenant_id' => $tenant,
        'panel' => 'test',
        'status_id' => test()->status[$tenant]['progress']->id,
        'priority_id' => test()->priority[$tenant]->id,
        'disposition_id' => null,
        ...$attributes,
    ]);
}

/**
 * Status and priority for one organization, plus another organization's rows
 * that sort first when the lookup is not scoped.
 *
 * @return array{status: TicketStatus, priority: TicketPriority}
 */
function panelLookups(string $panel): array
{
    TicketStatus::factory()->create(['tenant_id' => 2, 'panel' => $panel, 'order' => 0, 'display_name' => 'In Progress']);
    TicketPriority::factory()->create(['tenant_id' => 2, 'panel' => $panel, 'order' => 0, 'display_name' => 'Normal']);

    return [
        'status' => TicketStatus::factory()->create(['tenant_id' => 1, 'panel' => $panel, 'order' => 1, 'display_name' => 'In Progress']),
        'priority' => TicketPriority::factory()->create(['tenant_id' => 1, 'panel' => $panel, 'order' => 1, 'display_name' => 'Normal']),
    ];
}

/**
 * @param  list<int>  $ids
 * @return list<int>
 */
function sortedIds(array $ids): array
{
    sort($ids);

    return $ids;
}

function lookupTenancyOptionIds(mixed $field): array
{
    return collect($field->getOptions())->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
}
