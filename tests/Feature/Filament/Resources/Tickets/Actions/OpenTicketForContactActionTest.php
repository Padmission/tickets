<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\OpenTicketForContactAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\Fixtures\Models\Tenant;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * Panel test escalates to test2, the other team's panel, where its staff
 * open a question for one of test's supporters.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    $this->staff = User::factory()->create(['name' => 'Kevin McKee', 'email' => 'kevin@example.com']);
    $this->contact = User::factory()->create(['name' => 'Tess Support', 'email' => 'tess@example.com']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez', 'email' => 'maria@example.com']);
    $this->requester = User::factory()->create(['name' => 'Nina Patel', 'email' => 'nina@example.com']);

    TicketPlugin::get('test')->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test')->allSupportersQuery(fn () => User::query()->whereKey([$this->contact->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Padmission')->startsTickets();
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->staff->id));

    Filament::setCurrentPanel('test2');
    $this->login($this->staff);
});

function openForContact(): TestAction
{
    return TestAction::make(OpenTicketForContactAction::class);
}

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function contactQuestion(User $contact, array $data = []): array
{
    return [
        'contact_id' => $contact->id,
        'subject' => 'Load the utility allowance schedule',
        'message' => '<p>Tess called: can the schedule be loaded for every household?</p>',
        ...$data,
    ];
}

// The organization is picked first: picking one clears the person.
function askAt(Tenant $tenant, User $contact): Testable
{
    $page = Livewire::test(ListTickets::class)
        ->mountAction(openForContact())
        ->set('mountedActions.0.data.tenant_id', $tenant->id);

    foreach (contactQuestion($contact) as $field => $value) {
        $page->set("mountedActions.0.data.{$field}", $value);
    }

    return $page->callMountedAction();
}

function redrawnForm(Testable $page): string
{
    return $page->effects['partials']['action-modals.0'] ?? '';
}

describe('Who may open one', function () {
    beforeEach(function () {
        Gate::policy(Ticket::class, TicketPolicy::class);
    });

    it('is offered to the panel\'s own responders only', function () {
        Livewire::test(ListTickets::class)->assertActionVisible(openForContact());

        $this->login($this->contact);

        Livewire::test(ListTickets::class)->assertActionHidden(openForContact());
    });

    it('is not offered unless the host asks for it', function () {
        TicketPlugin::get('test2')->startsTickets(null);

        Livewire::test(ListTickets::class)->assertActionHidden(openForContact());
    });

    it('is not offered on the side that escalates', function () {
        Filament::setCurrentPanel('test');
        TicketPlugin::get('test')->allSupportersQuery(fn () => User::query()->whereKey($this->staff->id));

        Livewire::test(ListTickets::class)->assertActionHidden(openForContact());
    });
});

describe('The form', function () {
    it('asks for the rest only once a person is chosen, and offers only the organization\'s supporters', function () {
        $page = Livewire::test(ListTickets::class)
            ->mountAction(openForContact())
            ->assertMountedActionModalSee(['Person', 'Tess Support', 'tess@example.com', 'Maria Lopez', 'For a regular user\'s problem, their organization\'s support team opens the ticket.', 'Open ticket'])
            ->assertMountedActionModalDontSee('Nina Patel')
            ->assertSchemaComponentHidden('subject', 'mountedActionSchema0')
            ->set('mountedActions.0.data.contact_id', $this->contact->id);

        expect(redrawnForm($page))->toContain('Subject', 'Message to Tess Support', 'Tess Support gets this as the first message');
    });

    it('refuses anyone who does not answer the organization\'s tickets', function () {
        Livewire::test(ListTickets::class)
            ->callAction(openForContact(), contactQuestion($this->requester))
            ->assertHasActionErrors(['contact_id']);

        expect(Ticket::query()->withoutGlobalScopes()->exists())->toBeFalse();
    });

    it('keeps the subject plain text', function (string $subject, bool $accepted) {
        $page = Livewire::test(ListTickets::class)->callAction(openForContact(), contactQuestion($this->contact, ['subject' => $subject]));

        $accepted ? $page->assertHasNoActionErrors() : $page->assertHasActionErrors(['subject']);
    })->with([
        'a tag' => ['<b>Allowances</b>', false],
        'a script URL' => ['javascript:alert(1)', false],
        'a less-than sign' => ['Rent < 200 for every household', true],
    ]);
});

describe('What is opened', function () {
    it('opens a question from the contact, which the person who opened it handles and owes the answer on, and lands on it', function () {
        $page = Livewire::test(ListTickets::class)
            ->callAction(openForContact(), contactQuestion($this->contact))
            ->assertHasNoActionErrors()
            ->assertNotified('Ticket opened');

        $question = Ticket::query()->withoutGlobalScopes()->sole();
        $page->assertRedirect(TicketResource::getUrl('view', ['record' => $question]));

        $message = $question->ticketActivities()->where('type', ActivityType::Message)->sole();

        expect($question->panel)->toBe('test2')
            ->and($question->source_panel)->toBe('test')
            ->and($question->isSubmittedBy($this->contact))->toBeTrue()
            ->and((string) $question->assignee_id)->toBe((string) $this->staff->id)
            ->and($question->turn)->toBe(Turn::Supporter)
            ->and($question->isDirectQuestion())->toBeTrue()
            ->and($message->sender)->toBe(ActivitySender::Supporter)
            ->and((string) $message->user_id)->toBe((string) $this->staff->id)
            ->and(Ticket::query()->withoutGlobalScopes()->escalationsFrom('test')->pluck('id')->all())->toBe([$question->id]);
    });

    it('says in the history who opened it for whom, by the other team\'s name on the side that escalates', function () {
        Livewire::test(ListTickets::class)->callAction(openForContact(), contactQuestion($this->contact));

        $note = Ticket::query()->withoutGlobalScopes()->sole()->ticketActivities()->where('type', ActivityType::OpenedFor)->sole();

        expect($note->openedForNote($this->staff->id))->toBe('You opened this ticket for Tess Support')
            ->and($note->openedForNote($this->contact->id))->toBe('Padmission opened this for you')
            ->and($note->openedForNote($this->colleague->id))->toBe('Padmission opened this for Tess Support');
    });

    it('offers neither side any escalation options, and is offered by Add to an existing escalation', function () {
        Livewire::test(ListTickets::class)->callAction(openForContact(), contactQuestion($this->contact));
        $question = Ticket::query()->withoutGlobalScopes()->sole();

        Livewire::test(ViewTicket::class, ['record' => $question->id])
            ->assertSee('A question from Tess Support. No user\'s ticket is attached.')
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.child_tickets'));

        Filament::setCurrentPanel('test');
        $this->login($this->contact);

        Livewire::test(ViewTicket::class, ['record' => $question->id])
            ->assertSee(['Conversation with Padmission', 'Your own question. No user\'s ticket is attached.'])
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets_description.escalated_to_you_to', ['team' => 'Padmission']));

        $original = Ticket::factory()->open()->create(['panel' => 'test', 'linked_ticket_id' => null]);

        expect(LinkedTicketCandidates::openEscalations(Ticket::query(), $original)->pluck('id')->all())->toBe([$question->id]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertActionVisible(TestAction::make(AddToEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'));
    });

    it('is listed under the organization\'s Direct questions as waiting on the other team, and under the other team\'s tickets as its own', function () {
        Livewire::test(ListTickets::class)->callAction(openForContact(), contactQuestion($this->contact));
        $question = Ticket::query()->withoutGlobalScopes()->sole();

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my')
            ->assertCanSeeTableRecords([$question])
            ->assertSee('Direct question, not about a ticket');

        Filament::setCurrentPanel('test');
        $this->login($this->contact);

        listDirectQuestions()
            ->assertCanSeeTableRecords([$question])
            ->assertSee('Padmission');
    });
});

describe('Telling the contact', function () {
    beforeEach(function () {
        config(['padmission-tickets.default-notification-strategy' => NotificationStrategy::Immediate]);
        Notification::fake();
    });

    it('emails them that the other team opened it for them, with the message, and tells the person who opened it nothing', function () {
        Livewire::test(ListTickets::class)->callAction(openForContact(), contactQuestion($this->contact));

        Notification::assertSentTo($this->contact, TicketNotification::class, function (TicketNotification $notification): bool {
            if ($notification->notificationType !== 'created') {
                return false;
            }

            $html = (string) $notification->toMail($this->contact)->render();

            return str_contains($html, 'Padmission opened a ticket for you.')
                && str_contains($html, 'Padmission wrote:')
                && str_contains($html, 'can the schedule be loaded for every household?');
        });

        Notification::assertSentToTimes($this->contact, TicketNotification::class, 1);
        Notification::assertNotSentTo($this->staff, TicketNotification::class);
    });

    it('puts who opened it in their bell', function () {
        Livewire::test(ListTickets::class)->callAction(openForContact(), contactQuestion($this->contact));
        $question = Ticket::query()->withoutGlobalScopes()->sole();

        expect((new TicketNotification($question, new TicketCreatedEvent($question, $this->staff)))->toDatabase($this->contact)['body'])
            ->toBe('Padmission opened a ticket for you.');
    });
});

it('writes names with quotes, ampersands and angle brackets as text', function () {
    $this->contact->update(['name' => 'O\'Brien & <Sons>']);

    expect(invade(new OpenTicketForContactAction('open-ticket-for-contact'))->personOption($this->contact->refresh()))
        ->toBe('O&#039;Brien &amp; &lt;Sons&gt; <span class="pad-ti-start-email">tess@example.com</span>');

    $page = Livewire::test(ListTickets::class)->mountAction(openForContact());

    $page->set('mountedActions.0.data.contact_id', $this->contact->id)
        ->set('mountedActions.0.data.subject', 'Allowances')
        ->set('mountedActions.0.data.message', '<p>Hello</p>')
        ->callMountedAction()
        ->assertNotified(FilamentNotification::make()
            ->success()
            ->title('Ticket opened')
            ->body('O&#039;Brien &amp; &lt;Sons&gt; gets an email saying you opened it for them. It\'s assigned to you.'));
});

describe('Where tickets belong to organizations', function () {
    beforeEach(function () {
        foreach (['tickets', 'ticket_statuses', 'ticket_priorities'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('tenant_id')->nullable());
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('takes_tickets')->default(true);
        });

        config()->set('padmission-tickets.tenancy.enabled', true);
        config()->set('padmission-tickets.tenancy.tenancy_model', Tenant::class);

        $this->org = Tenant::query()->create(['name' => 'HomeNow Indy']);
        $this->other = Tenant::query()->create(['name' => 'Farrell Ltd']);
        $this->off = Tenant::query()->create(['name' => 'Tickets Off Inc', 'takes_tickets' => false]);

        foreach ([$this->org, $this->other] as $tenant) {
            TicketStatus::factory()->create(['tenant_id' => $tenant->id, 'panel' => 'test2', 'order' => 0, 'display_name' => "{$tenant->name} New"]);
            TicketPriority::factory()->create(['tenant_id' => $tenant->id, 'panel' => 'test2', 'order' => 0, 'display_name' => "{$tenant->name} Normal"]);
        }

        // Each organization's supporters, pinned through the ticket it is given, as hosts pin them.
        TicketPlugin::get('test')->allSupportersQuery(fn (?Ticket $ticket = null) => User::query()->whereKey(match ((int) $ticket?->tenant_id) {
            $this->org->id => [$this->contact->id],
            $this->other->id => [$this->colleague->id],
            default => [],
        }));
        TicketPlugin::get('test2')->ticketTenantsQuery(fn () => Tenant::query()->where('takes_tickets', true));
    });

    it('offers only the organizations the host lets it, and only each one\'s own supporters', function () {
        $search = invade(new OpenTicketForContactAction('open-ticket-for-contact'))->searchTenants('');

        expect($search)->toBe([$this->other->id => 'Farrell Ltd', $this->org->id => 'HomeNow Indy']);

        $page = Livewire::test(ListTickets::class)
            ->mountAction(openForContact())
            ->assertSchemaComponentHidden('contact_id', 'mountedActionSchema0')
            ->set('mountedActions.0.data.tenant_id', $this->org->id);

        expect(redrawnForm($page))->toContain('Tess Support')->not->toContain('Maria Lopez');
    });

    it('lists the organizations it may open tickets for as soon as it opens, by name, without typing', function () {
        Livewire::test(ListTickets::class)
            ->mountAction(openForContact())
            ->assertSchemaComponentExists('tenant_id', checkComponentUsing: fn (Select $field): bool => $field->getOptions() === [
                $this->other->id => 'Farrell Ltd',
                $this->org->id => 'HomeNow Indy',
            ]);
    });

    it('lists the first 50 up front, and search narrows them and finds the rest', function () {
        foreach (range(1, 55) as $number) {
            Tenant::query()->create(['name' => sprintf('Agency %02d', $number)]);
        }

        Livewire::test(ListTickets::class)
            ->mountAction(openForContact())
            ->assertSchemaComponentExists('tenant_id', checkComponentUsing: fn (Select $field): bool => count($field->getOptions()) === 50
                && array_values($field->getOptions())[0] === 'Agency 01'
                && ! in_array('Agency 55', $field->getOptions(), true)
                && array_values($field->getSearchResults('Agency 55')) === ['Agency 55']
                && array_values($field->getSearchResults('Home')) === ['HomeNow Indy']
                && $field->getSearchResults('Tickets Off') === []);

        // One found only by search is still accepted.
        Livewire::test(ListTickets::class)
            ->mountAction(openForContact())
            ->set('mountedActions.0.data.tenant_id', Tenant::query()->where('name', 'Agency 55')->value('id'))
            ->callMountedAction()
            ->assertHasActionErrors(['contact_id' => 'required'])
            ->assertHasNoActionErrors(['tenant_id']);
    });

    it('lists the chosen organization\'s supporters as soon as the person select opens', function () {
        Livewire::test(ListTickets::class)
            ->mountAction(openForContact())
            ->set('mountedActions.0.data.tenant_id', $this->org->id)
            ->assertSchemaComponentExists('contact_id', checkComponentUsing: fn (Select $field): bool => array_keys($field->getOptions()) === [$this->contact->id]);
    });

    it('opens the question in the chosen organization, with its status and priority', function () {
        askAt($this->org, $this->contact)->assertHasNoActionErrors();

        $question = Ticket::query()->withoutGlobalScopes()->sole();

        expect((int) $question->tenant_id)->toBe($this->org->id)
            ->and($question->status->display_name)->toBe('HomeNow Indy New')
            ->and($question->priority->display_name)->toBe('HomeNow Indy Normal');
    });

    it('refuses another organization\'s supporter, or an organization it may not open tickets for', function (string $tenant) {
        askAt($this->{$tenant}, $this->contact)->assertHasActionErrors();

        expect(Ticket::query()->withoutGlobalScopes()->exists())->toBeFalse();
    })->with([
        'another organization\'s supporter' => ['other'],
        'an organization with tickets off' => ['off'],
    ]);
});
