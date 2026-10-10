<?php

use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\RemoveFromEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ViewOriginalConversationAction;
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
        escalationFrom();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible(escalationAction(CreateLinkedTicketAction::class))
            ->assertActionVisible(escalationAction(AddToEscalationAction::class))
            ->assertSee(__('padmission-tickets::tickets.actions.add_to_escalation.help_to', ['team' => 'Platform Support']));
    });

    it('offers no way to add to an escalation when the picker would have none to list', function (Closure $escalations) {
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);
        $escalations();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible(escalationAction(CreateLinkedTicketAction::class))
            ->assertDontSee(__('padmission-tickets::tickets.actions.add_to_escalation.label'))
            ->assertDontSee(__('padmission-tickets::tickets.actions.add_to_escalation.help_to', ['team' => 'Platform Support']));
    })->with([
        'no escalations at all' => fn (): Closure => fn () => null,
        'only a closed one' => fn (): Closure => fn () => escalationFrom(state: 'closed'),
        'only another panel\'s' => fn (): Closure => fn () => escalationFrom('test2'),
    ]);

    it('offers only open escalations', function () {
        $ticket = Ticket::factory()->open()->create();
        $open = escalationFrom();
        $closed = escalationFrom(state: 'closed');

        expect(LinkedTicketCandidates::openEscalations(Ticket::query(), $ticket)->pluck('id'))
            ->toContain($open->id)
            ->not->toContain($closed->id);
    });

    it('offers only escalations this panel opened, never the other panel\'s own tickets', function () {
        $ticket = Ticket::factory()->open()->create();
        $bySource = escalationFrom();
        $byOriginal = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => null]);
        Ticket::factory()->open()->create(['linked_ticket_id' => $byOriginal->id]);
        $neverEscalated = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test']);
        $fromAnotherPanel = escalationFrom('test3');

        expect(LinkedTicketCandidates::openEscalations(Ticket::query(), $ticket)->pluck('id')->all())
            ->toContain($bySource->id, $byOriginal->id)
            ->not->toContain($neverEscalated->id)
            ->not->toContain($fromAnotherPanel->id);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->mountAction(escalationAction(AddToEscalationAction::class))
            ->assertMountedActionModalSee("#{$bySource->id}")
            ->assertMountedActionModalDontSee("#{$neverEscalated->id}");

        expect(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $neverEscalated->id))->toBe(TicketEscalationLinks::NOT_LINKABLE)
            ->and($ticket->refresh()->linked_ticket_id)->toBeNull()
            ->and(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $fromAnotherPanel->id))->toBe(TicketEscalationLinks::NOT_LINKABLE)
            ->and(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $bySource->id))->toBeNull()
            ->and($ticket->refresh()->linked_ticket_id)->toBe($bySource->id);
    });

    it('adds the ticket and records it on both tickets', function () {
        $escalation = escalationFrom();
        $requester = User::factory()->create(['name' => 'Rita Requester']);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null, 'submitter_id' => $requester->id]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction(escalationAction(AddToEscalationAction::class), ['escalation' => $escalation->id])
            ->assertHasNoActionErrors();

        expect($ticket->refresh()->linked_ticket_id)->toBe($escalation->id)
            ->and($ticket->ticketActivities()->where('type', ActivityType::AddedToEscalation)->first()->plainTextContent())
            ->toBe('Added to the escalation to Platform Support by Tess Support')
            ->and($escalation->ticketActivities()->where('type', ActivityType::OriginalAdded)->latest('id')->first()->plainTextContent())
            ->toBe('Rita Requester\'s ticket added to this escalation by Tess Support');
    });

    it('says which escalation the ticket is part of, how many others share it and who handles it', function () {
        // Waiting on the other team, so the status line offers no link of its own.
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => auth()->id(), 'turn' => Turn::Supporter]);
        Ticket::factory()->count(2)->create(['linked_ticket_id' => $escalation->id]);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee('Escalated to Platform Support, with 2 other tickets. You handle the conversation with Platform Support.')
            ->assertDontSee('View escalation')
            ->assertActionVisible(escalationAction('open-escalation'))
            ->assertActionHasUrl(escalationAction('open-escalation'), ViewTicket::getUrl(['record' => $escalation, 'linked' => $ticket->id]))
            ->assertActionVisible(escalationAction(RemoveFromEscalationAction::class));
    });

    it('removes the ticket after confirmation and records it on both tickets', function () {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction(escalationAction(RemoveFromEscalationAction::class));

        expect($ticket->refresh()->linked_ticket_id)->toBeNull()
            ->and($ticket->ticketActivities()->where('type', ActivityType::RemovedFromEscalation)->first()->plainTextContent())
            ->toBe('Removed from the escalation to Platform Support by Tess Support')
            ->and($escalation->ticketActivities()->where('type', ActivityType::OriginalRemoved)->exists())->toBeTrue();
    });

    // The chat and the emails read the note's content; the original's transcript reads its plain text.
    it('names the team a ticket was escalated to, and the escalation one was added to, wherever the note is read', function (?string $team, string $escalated, string $added) {
        TicketPlugin::get('test2')->supportTeamName($team);

        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test']);
        [$first, $second] = Ticket::factory()->open()->count(2)->create();
        $links = resolve(TicketEscalationLinks::class);
        $links->linkNewEscalation($first, $escalation);
        $links->addToEscalation($second, $escalation->id);

        $note = fn (Ticket $ticket, ActivityType $type): TicketActivity => $ticket->ticketActivities()->where('type', $type)->sole();

        expect(strip_tags($note($first, ActivityType::Escalated)->content))->toBe($escalated)
            ->and($note($first, ActivityType::Escalated)->plainTextContent())->toBe($escalated)
            ->and(strip_tags($note($second, ActivityType::AddedToEscalation)->content))->toBe($added)
            ->and($note($second, ActivityType::AddedToEscalation)->plainTextContent())->toBe($added);
    })->with([
        'a named team' => ['Platform Support', 'Escalated to Platform Support by Tess Support', 'Added to the escalation to Platform Support by Tess Support'],
        'no team name' => [null, 'Escalated to the other team by Tess Support', 'Added to the escalation by Tess Support'],
    ]);

    it('links a history note to the other ticket only when the viewer can open it', function () {
        $escalation = escalationFrom();
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);
        resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $escalation->id);

        $note = fn (): string => $ticket->ticketActivities()->where('type', ActivityType::AddedToEscalation)->first()->content;

        expect($note())->toContain('title="Ticket #'.$escalation->id.'"', '>the escalation to Platform Support</a>');

        TicketPlugin::get()->customizeTicketQuery(fn ($query) => $query->whereKeyNot($escalation->id));

        expect($note())->toBe('Added to the escalation to Platform Support by Tess Support');
    });

    it('escapes names in history notes', function () {
        $escalation = escalationFrom();
        $requester = User::factory()->create(['name' => '<b>Rita</b>']);
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null, 'submitter_id' => $requester->id]);
        resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $escalation->id);

        expect($escalation->ticketActivities()->where('type', ActivityType::OriginalAdded)->latest('id')->first()->content)
            ->toContain('&lt;b&gt;Rita&lt;/b&gt;')
            ->not->toContain('<b>');
    });

    it('names the other ticket plainly in older notes whose ticket is gone', function () {
        $ticket = Ticket::factory()->open()->create();
        $ticket->addTicketActivity(ActivityType::AddedToEscalation, ActivitySender::System, $this->user->id, ['escalation' => 999999]);

        expect($ticket->ticketActivities()->where('type', ActivityType::AddedToEscalation)->first()->content)
            ->toBe('Added to the escalation by Tess Support');
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

    it('shows each original by number and requester, without repeating the organization the chat header names', function () {
        TicketPlugin::get()->describeTicketOriginUsing(fn (Ticket $ticket): string => "Org of {$ticket->id}");

        $requester = User::factory()->create(['name' => 'Rita Requester']);
        $escalation = Ticket::factory()->open()->create();
        $original = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id, 'submitter_id' => $requester->id]);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->call('closeLinked')
            ->assertSee("#{$original->id} · Rita Requester")
            ->assertDontSee("Org of {$original->id}");
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

        TicketPlugin::get()->linkedConversationView(TicketPlugin::LINKED_VIEW_MODAL);

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

    it('leaves a closed escalation\'s originals as they were, and offers no Link existing on it', function () {
        $escalation = Ticket::factory()->closed()->create(['panel' => 'test']);
        $linked = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);
        $free = Ticket::factory()->create(['panel' => 'test2']);

        expect(resolve(TicketEscalationLinks::class)->syncOriginals($escalation, [$free->id]))->toBeFalse()
            ->and($linked->refresh()->linked_ticket_id)->toBe($escalation->id)
            ->and($free->refresh()->linked_ticket_id)->toBeNull();

        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertActionHidden('linkOriginals')
            ->assertDontSeeHtml('aria-label="'.__('padmission-tickets::tickets.actions.remove_from_escalation.label').'"');
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

describe('Link changes leave the original untouched', function () {
    beforeEach(function () {
        $this->escalation = escalationFrom();
        $this->original = Ticket::factory()->open()->create(['linked_ticket_id' => null]);
        $this->updatedAt = $this->original->refresh()->updated_at->toDateTimeString();

        $this->travel(1)->hour();
    });

    it('keeps updated_at when the original is linked', function () {
        resolve(TicketEscalationLinks::class)->addToEscalation($this->original, $this->escalation->id);

        expect($this->original->refresh())
            ->linked_ticket_id->toBe($this->escalation->id)
            ->updated_at->toDateTimeString()->toBe($this->updatedAt);
    });

    it('keeps updated_at when the original is removed', function () {
        Ticket::withoutTimestamps(fn () => $this->original->update(['linked_ticket_id' => $this->escalation->id]));

        resolve(TicketEscalationLinks::class)->removeFromEscalation($this->original);

        expect($this->original->refresh())
            ->linked_ticket_id->toBeNull()
            ->updated_at->toDateTimeString()->toBe($this->updatedAt);
    });

    it('keeps updated_at when the escalation drops the original', function () {
        Ticket::withoutTimestamps(fn () => $this->original->update(['linked_ticket_id' => $this->escalation->id]));

        resolve(TicketEscalationLinks::class)->syncOriginals($this->escalation, []);

        expect($this->original->refresh())
            ->linked_ticket_id->toBeNull()
            ->updated_at->toDateTimeString()->toBe($this->updatedAt);
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

    it('lists direct questions that belong to another tenant', function () {
        $question = CustomTicket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'tenant_id' => 2]);
        $question->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);

        $tab = listDirectQuestions()->instance()->getTabs()['all'];

        expect($tab->modifyQuery(CustomTicket::query()->withoutGlobalScope('viewer-tenant'))->pluck('id'))
            ->toContain($question->id);
    });
});

describe('A dialog left open while the ticket was escalated elsewhere', function () {
    it('says the ticket is already escalated instead of doing nothing', function (string $action, array $data) {
        $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);
        $target = escalationFrom();

        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->mountAction(escalationAction($action))
            ->fillForm($action === AddToEscalationAction::class ? ['escalation' => $target->id] : $data);

        $ticket->forceFill(['linked_ticket_id' => escalationFrom()->id])->saveQuietly();

        // As the browser does: Filament's own test helper stops at an action it can no longer find.
        $page->call('callMountedAction')
            ->assertNotified(Notification::make()
                ->danger()
                ->title(__('padmission-tickets::tickets.resources.tickets.link_refused.title'))
                ->body(__('padmission-tickets::tickets.resources.tickets.link_refused.already_escalated')));
    })->with([
        'Escalate' => [CreateLinkedTicketAction::class, ['subject' => 'Rent is wrong', 'message' => '<p>Please check.</p>']],
        'Add to an existing escalation' => [AddToEscalationAction::class, []],
    ]);
});
