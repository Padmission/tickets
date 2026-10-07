<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Padmission\Tickets\AssignmentStrategies\AssignDefaultUser;
use Padmission\Tickets\ChatWidgetConfig;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\OpenTicketForContactAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\StartTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    $this->supporter = $this->login(User::factory()->create(['name' => 'Tess Support', 'email' => 'tess@example.com']));
    $this->requester = User::factory()->create(['name' => 'Nina Patel', 'email' => 'nina@example.com']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');
});

function startTicket(): TestAction
{
    return TestAction::make(StartTicketAction::class);
}

// A live field draws the open modal again as this partial.
function redrawnModal(Testable $page): string
{
    return $page->effects['partials']['action-modals.0'] ?? '';
}

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function forRequester(User $requester, array $data = []): array
{
    return [
        'kind' => StartTicketAction::ORGANIZATION,
        'requester_id' => $requester->id,
        'assign' => 'me',
        'subject' => 'Pay stubs won\'t upload',
        'message' => '<p>Hi Nina, I opened this for the upload that spins.</p>',
        ...$data,
    ];
}

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function question(array $data = []): array
{
    return [
        'kind' => StartTicketAction::ESCALATION,
        'subject' => 'Load the new utility allowance schedule',
        'message' => '<p>Can the schedule be loaded once for the whole agency?</p>',
        ...$data,
    ];
}

describe('Who may start one', function () {
    beforeEach(function () {
        Gate::policy(Ticket::class, TicketPolicy::class);
    });

    it('is offered to the panel\'s supporters only', function () {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($this->supporter->id));

        Livewire::test(ListTickets::class)->assertActionVisible(startTicket());

        $this->login($this->requester);

        Livewire::test(ListTickets::class)->assertActionHidden(startTicket());
    });

    it('is not offered in a panel that receives other teams\' escalations', function () {
        Filament::setCurrentPanel('test2');

        Livewire::test(ListTickets::class)->assertActionHidden(startTicket());
    });

    it('leaves that panel\'s New ticket to the form for its own side when the host asks for one', function () {
        Filament::setCurrentPanel('test2');
        TicketPlugin::get('test2')->startsTickets();

        Livewire::test(ListTickets::class)
            ->assertActionHidden(startTicket())
            ->assertActionVisible(TestAction::make(OpenTicketForContactAction::class));
    });
});

describe('The first choice', function () {
    it('preselects the side of the tab it was opened from', function (string $tab, ?string $kind) {
        Livewire::test(ListTickets::class)
            ->set('activeTab', $tab)
            ->mountAction(startTicket())
            ->assertSchemaStateSet(['kind' => $kind], 'mountedActionSchema0');
    })->with([
        'All Tickets' => ['all', StartTicketAction::ORGANIZATION],
        'My Tickets' => ['my', StartTicketAction::ORGANIZATION],
        'Escalations' => ['linked', StartTicketAction::ESCALATION],
        'My Escalations' => ['my_linked', StartTicketAction::ESCALATION],
        'empty tab falls back to My Tickets' => ['', StartTicketAction::ORGANIZATION],
    ]);

    it('names each side\'s submit button', function () {
        $page = Livewire::test(ListTickets::class)
            ->set('activeTab', 'all')
            ->mountAction(startTicket())
            ->assertMountedActionModalSee('Create ticket')
            ->set('mountedActions.0.data.kind', StartTicketAction::ESCALATION);

        expect(redrawnModal($page))
            ->toContain('Send to Platform Support', 'Message to Platform Support', 'Escalate that ticket instead')
            ->not->toContain('Create ticket');
    });

    it('shows the organization\'s form alone when there is nobody to escalate to', function () {
        TicketPlugin::get()->allowLinkedTicketsTo([]);

        Livewire::test(ListTickets::class)
            ->mountAction(startTicket())
            ->assertMountedActionModalDontSee('A question for')
            ->assertMountedActionModalSee('Requested by');

        Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester, ['kind' => null]))
            ->assertHasNoActionErrors();

        expect(Ticket::query()->sole()->isSubmittedBy($this->requester))->toBeTrue();
    });
});

describe('A ticket for someone in the organization', function () {
    it('opens an ordinary ticket for them that waits on support, and lands on it', function () {
        $page = Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester))
            ->assertHasNoActionErrors()
            ->assertNotified('Ticket created');

        $ticket = Ticket::query()->sole();
        $page->assertRedirect(TicketResource::getUrl('view', ['record' => $ticket]));

        $activities = $ticket->ticketActivities()->orderBy('id')->get();

        expect($ticket->panel)->toBe('test')
            ->and($ticket->isSubmittedBy($this->requester))->toBeTrue()
            ->and((string) $ticket->assignee_id)->toBe((string) $this->supporter->id)
            ->and($ticket->turn)->toBe(Turn::Supporter)
            ->and($ticket->subject)->toBe('Pay stubs won\'t upload')
            ->and($ticket->isEscalation())->toBeFalse()
            ->and($activities->pluck('type')->all())->toBe([ActivityType::OpenedFor, ActivityType::Message])
            ->and($activities[1]->sender)->toBe(ActivitySender::Supporter)
            ->and((string) $activities[1]->user_id)->toBe((string) $this->supporter->id)
            ->and($activities[1]->content)->toContain('I opened this for the upload that spins.');
    });

    it('says in the history who opened it for whom', function () {
        Livewire::test(ListTickets::class)->callAction(startTicket(), forRequester($this->requester));

        $note = Ticket::query()->sole()->ticketActivities()->where('type', ActivityType::OpenedFor)->sole();

        expect($note->openedForNote($this->supporter->id))->toBe('You opened this ticket for Nina Patel')
            ->and($note->openedForNote($this->requester->id))->toBe('Tess Support opened this ticket for you')
            ->and($note->openedForNote(null))->toBe('Tess Support opened this ticket for Nina Patel');
    });

    it('assigns it to a colleague from the supporter pool', function () {
        $colleague = User::factory()->create(['name' => 'Maria Lopez']);

        Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester, ['assign' => 'colleague', 'assignee_id' => $colleague->id]))
            ->assertHasNoActionErrors()
            ->assertNotified();

        expect((string) Ticket::query()->sole()->assignee_id)->toBe((string) $colleague->id);
    });

    it('refuses a colleague outside the supporter pool', function () {
        $colleague = User::factory()->create();
        $outsider = User::factory()->create();
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->supporter->id, $colleague->id]));

        Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester, ['assign' => 'colleague', 'assignee_id' => $outsider->id]));

        expect(Ticket::query()->exists())->toBeFalse();
    });

    it('leaves Automatic to the panel\'s assignment strategy, and offers it only when there is one', function () {
        $default = User::factory()->create(['name' => 'Default Person']);

        Livewire::test(ListTickets::class)
            ->mountAction(startTicket())
            ->assertMountedActionModalDontSee('Automatic');

        TicketPlugin::get()->assignmentStrategy(new AssignDefaultUser($default->id));

        Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester, ['assign' => 'auto']))
            ->assertHasNoActionErrors();

        expect((string) Ticket::query()->sole()->assignee_id)->toBe((string) $default->id);
    });

    it('searches the requesters by name and email, showing each email, and never offers the supporter themselves', function () {
        $namesake = User::factory()->create(['name' => 'Nina Patel', 'email' => 'nina.p@example.org']);

        $search = fn (string $term): array => invade(new StartTicketAction('start-ticket'))->searchRequesters($term);

        expect($search('Nina'))->toHaveKeys([$this->requester->id, $namesake->id])
            ->and($search('Nina')[$namesake->id])->toBe('Nina Patel <span class="pad-ti-start-email">nina.p@example.org</span>')
            ->and($search('nina.p@'))->toHaveKeys([$namesake->id])
            ->and($search('Tess'))->toBe([]);
    });

    it('opens it only for someone the host lets the panel pick', function () {
        TicketPlugin::get()->requestersQuery(fn () => User::query()->whereKeyNot($this->requester->id));

        Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester))
            ->assertHasActionErrors(['requester_id']);

        expect(Ticket::query()->exists())->toBeFalse();
    });

    it('keeps its attachments on the first message', function () {
        Storage::fake(config('padmission-tickets.attachments.disk'));
        TicketPlugin::get()->showChatWidget(config: ChatWidgetConfig::make()->allowFileUploads());

        Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester, ['attachments' => [UploadedFile::fake()->create('pay-stub.pdf', 12, 'application/pdf')]]))
            ->assertHasNoActionErrors();

        $attachment = Ticket::query()->sole()->ticketActivities()->where('type', ActivityType::Message)->sole()->attachments()->sole();

        expect($attachment->filename)->toBe('pay-stub.pdf')
            ->and($attachment->mime_type)->toBe('application/pdf');

        Storage::disk(config('padmission-tickets.attachments.disk'))->assertExists($attachment->filepath);
    });
});

describe('Telling the requester', function () {
    beforeEach(function () {
        config(['padmission-tickets.default-notification-strategy' => NotificationStrategy::Immediate]);
        Notification::fake();
    });

    it('sends them the new-ticket email saying who opened it for them, with the message, and nothing else', function () {
        Livewire::test(ListTickets::class)->callAction(startTicket(), forRequester($this->requester));

        $ticket = Ticket::query()->sole();

        Notification::assertSentTo($this->requester, TicketNotification::class, function (TicketNotification $notification): bool {
            if ($notification->notificationType !== 'created') {
                return false;
            }

            $html = (string) $notification->toMail($this->requester)->render();

            return str_contains($html, 'Tess Support opened this ticket for you.')
                && str_contains($html, 'Tess Support wrote:')
                && str_contains($html, 'I opened this for the upload that spins.');
        });

        Notification::assertSentToTimes($this->requester, TicketNotification::class, 1);
        Notification::assertNotSentTo($this->supporter, TicketNotification::class);

        expect($ticket->ticketActivities()->count())->toBe(2);
    });

    it('tells a colleague the ticket was assigned to them', function () {
        $colleague = User::factory()->create(['name' => 'Maria Lopez']);

        Livewire::test(ListTickets::class)
            ->callAction(startTicket(), forRequester($this->requester, ['assign' => 'colleague', 'assignee_id' => $colleague->id]));

        $ticket = Ticket::query()->sole();

        Notification::assertSentTo($colleague, TicketNotification::class, function (TicketNotification $notification) use ($colleague, $ticket): bool {
            $mail = $notification->toMail($colleague);

            return $notification->notificationType === 'created'
                && $mail->subject === "Ticket #{$ticket->id} assigned to you – Pay stubs won't upload"
                && $mail->viewData['headline'] === 'Ticket Assigned'
                && $mail->viewData['intro'] === 'A ticket has been assigned to you for handling.';
        });
    });

    it('puts who opened it in the bell, not the message', function () {
        Livewire::test(ListTickets::class)->callAction(startTicket(), forRequester($this->requester));

        $created = new TicketNotification(Ticket::query()->sole(), new TicketCreatedEvent(Ticket::query()->sole(), $this->supporter));

        expect($created->toDatabase($this->requester)['body'])->toBe('Tess Support opened this ticket for you.');
    });

    it('still tells them of the supporter\'s next reply', function () {
        Livewire::test(ListTickets::class)->callAction(startTicket(), forRequester($this->requester));

        $ticket = Ticket::query()->sole();

        Notification::fake();

        $ticket->ticketActivities()->create(['type' => ActivityType::Message, 'sender' => ActivitySender::Supporter, 'content' => 'Fixed it.']);

        Notification::assertSentTo($this->requester, TicketNotification::class, fn (TicketNotification $notification): bool => $notification->notificationType === 'activity');
    });
});

describe('A question for the team the panel escalates to', function () {
    it('opens an escalation with no originals, owned by the supporter and waiting on the other team', function () {
        $page = Livewire::test(ListTickets::class)
            ->set('activeTab', 'linked')
            ->callAction(startTicket(), question())
            ->assertHasNoActionErrors()
            ->assertNotified('Sent to Platform Support');

        $question = Ticket::query()->withoutGlobalScopes()->sole();
        $page->assertRedirect(TicketResource::getUrl('view', ['record' => $question]));

        expect($question->panel)->toBe('test2')
            ->and($question->source_panel)->toBe('test')
            ->and($question->isSubmittedBy($this->supporter))->toBeTrue()
            ->and($question->turn)->toBe(Turn::Supporter)
            ->and($question->isEscalation())->toBeTrue()
            ->and($question->isDirectQuestion())->toBeTrue()
            ->and($question->ticketActivities()->where('type', ActivityType::Message)->sole()->sender)->toBe(ActivitySender::User)
            ->and(Ticket::query()->withoutGlobalScopes()->escalationsFrom('test')->pluck('id')->all())->toBe([$question->id]);
    });

    it('reaches the other team as a new escalation does', function () {
        config(['padmission-tickets.default-notification-strategy' => NotificationStrategy::Immediate]);
        Notification::fake();
        $staff = User::factory()->create(['name' => 'Mike Shore']);

        Livewire::test(ListTickets::class)->callAction(startTicket(), question());

        Notification::assertSentTo($staff, TicketNotification::class, fn (TicketNotification $notification): bool => $notification->notificationType === 'activity');
        Notification::assertNotSentTo($this->supporter, TicketNotification::class);
    });

    it('offers neither side any escalation options', function () {
        Livewire::test(ListTickets::class)->callAction(startTicket(), question());
        $question = Ticket::query()->withoutGlobalScopes()->sole();

        Livewire::test(ViewTicket::class, ['record' => $question->id])
            ->assertSee('Conversation with Platform Support')
            ->assertSee('Your own question. No user\'s ticket is attached.')
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets_description.escalated_to_you_to', ['team' => 'Platform Support']))
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.child_tickets'));

        Filament::setCurrentPanel('test2');
        $this->login(User::factory()->create());

        Livewire::test(ViewTicket::class, ['record' => $question->id])
            ->assertSee('A question from Tess Support. No user\'s ticket is attached.')
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.child_tickets'))
            ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.child_tickets_placeholder'));
    });

    it('is offered by Add to an existing escalation, and is an escalation like any other once an original joins it', function () {
        Livewire::test(ListTickets::class)->callAction(startTicket(), question());
        $question = Ticket::query()->withoutGlobalScopes()->sole();
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

        expect(LinkedTicketCandidates::openEscalations(Ticket::query(), $original)->pluck('id')->all())->toBe([$question->id]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertActionVisible(TestAction::make(AddToEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'));

        resolve(TicketEscalationLinks::class)->addToEscalation($original, $question->id);
        $question->refresh();

        expect($question->isDirectQuestion())->toBeFalse();

        Livewire::test(ViewTicket::class, ['record' => $question->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets_description.escalated_to_you_to', ['team' => 'Platform Support']));
    });

    it('is listed under Escalations as waiting on the other team', function () {
        Livewire::test(ListTickets::class)->callAction(startTicket(), question());
        $question = Ticket::query()->withoutGlobalScopes()->sole();

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'linked')
            ->assertCanSeeTableRecords([$question])
            ->assertSee(['Platform Support', 'Direct question, not about a ticket'])
            ->assertDontSee('Not linked to any ticket');

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'all')
            ->assertCanNotSeeTableRecords([$question]);
    });
});

describe('What is written', function () {
    it('keeps the subject plain text', function (string $subject, bool $accepted) {
        $page = Livewire::test(ListTickets::class)->callAction(startTicket(), forRequester($this->requester, ['subject' => $subject]));

        $accepted ? $page->assertHasNoActionErrors() : $page->assertHasActionErrors(['subject']);
    })->with([
        'a tag' => ['<b>Pay stubs</b>', false],
        'a script URL' => ['javascript:alert(1)', false],
        'a less-than sign' => ['Rent < 200 on the recert', true],
    ]);

    it('writes names with quotes, ampersands and angle brackets as text', function () {
        $requester = User::factory()->create(['name' => 'O\'Brien & <Sons>', 'email' => 'obrien@example.com']);
        $this->supporter->update(['name' => 'Tess & <Co>']);

        $search = invade(new StartTicketAction('start-ticket'))->searchRequesters('Brien');

        expect($search[$requester->id])->toBe('O&#039;Brien &amp; &lt;Sons&gt; <span class="pad-ti-start-email">obrien@example.com</span>');

        $page = Livewire::test(ListTickets::class)
            ->set('activeTab', 'all')
            ->mountAction(startTicket())
            ->set('mountedActions.0.data.requester_id', $requester->id);

        expect(redrawnModal($page))
            ->toContain('Message to O&#039;Brien &amp; &lt;Sons&gt;', 'O&#039;Brien &amp; &lt;Sons&gt; gets the new-ticket email')
            ->not->toContain('<Sons>');

        $page->set('mountedActions.0.data.subject', 'Pay stubs')
            ->set('mountedActions.0.data.message', '<p>Hello</p>')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified(FilamentNotification::make()
                ->success()
                ->title('Ticket created')
                ->body('O&#039;Brien &amp; &lt;Sons&gt; gets an email saying you opened this ticket for them. It\'s assigned to you.'));

        $note = Ticket::query()->sole()->ticketActivities()->where('type', ActivityType::OpenedFor)->sole();

        expect($note->openedForNote(null))->toBe('Tess &amp; &lt;Co&gt; opened this ticket for O&#039;Brien &amp; &lt;Sons&gt;');
    });
});
