<?php

use Filament\Actions\Testing\TestAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ViewOriginalConversationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
});

it('describes the submitter and assignee with the host description', function () {
    $submitter = User::factory()->create();
    $assignee = User::factory()->create();

    TicketPlugin::get()->describeUsersUsing(fn (Model $user): string => "Role of {$user->getKey()}");

    $ticket = Ticket::factory()->create(['submitter_id' => $submitter->id, 'assignee_id' => $assignee->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee("Role of {$submitter->id}")
        ->assertSee("Role of {$assignee->id}");
});

it('says a ticket nobody is assigned to is unassigned', function () {
    $ticket = Ticket::factory()->create(['assignee_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.unassigned'));
});

it('names the support team when the assignee is outside the viewer\'s scope', function () {
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $ticket = Ticket::factory()->create(['panel' => 'test2', 'submitter_id' => auth()->id(), 'assignee_id' => User::factory()->create()->id]);

    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($ticket->assignee_id));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('Platform Support')
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.unassigned'));
})->after(fn () => User::clearBootedModels());

it('names the person the team works an escalation through, on the page and beside the original', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->linkedConversationView(TicketPlugin::LINKED_VIEW_BESIDE);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');
    $person = User::factory()->create(['name' => 'Kevin McKee']);

    TicketPlugin::get('test2')->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('acting-tenant'));
    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($person->id));

    $escalation = Ticket::factory()->create(['panel' => 'test2', 'submitter_id' => auth()->id(), 'assignee_id' => $person->id]);
    $original = Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->assertSee('Kevin McKee')
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.hints.assignee_elsewhere_to', ['team' => 'Platform Support']));

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->assertDontSee('Kevin McKee')
        ->call('showLinked', $escalation->id)
        ->assertSee('Kevin McKee');
})->after(fn () => User::clearBootedModels());

it('sends the browser only the name of a person found past the viewer\'s scopes, and keeps them after a reload', function () {
    $person = User::factory()->create(['name' => 'Kevin McKee', 'email' => 'kevin@padmission.test']);

    TicketPlugin::get('test2')->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('acting-tenant'));
    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($person->id));

    $escalation = Ticket::factory()->create(['panel' => 'test2', 'submitter_id' => auth()->id(), 'assignee_id' => $person->id]);

    $page = Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->call('getRecord')
        ->assertReturned(fn (mixed $record): bool => str_contains((string) json_encode($record), 'Kevin McKee')
            && ! str_contains((string) json_encode($record), 'kevin@padmission.test'));

    $record = $page->instance()->getRecord();
    expect($record->assignee->email)->toBe('kevin@padmission.test');

    $record->refresh();
    expect($page->instance()->getRecord()->assignee?->getKey())->toBe($person->id);

    $record->load('assignee');
    expect($page->instance()->getRecord()->assignee?->getKey())->toBe($person->id);
})->after(fn () => User::clearBootedModels());

it('adds the host ticket details to the ticket page', function () {
    TicketPlugin::get()->additionalTicketDetails(fn (): array => [
        TextEntry::make('organization')->label('Organization')->state('Acme Housing'),
    ]);

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('Acme Housing');
});

it('adds the host columns to the ticket list', function () {
    (new TicketStatusSeeder)->run();

    TicketPlugin::get()->additionalTableColumns(fn (): array => [
        TextColumn::make('organization')->label('Organization')->state('Acme Housing'),
    ]);

    Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->assertTableColumnExists('organization')
        ->assertSee('Acme Housing');
});

describe('Original conversation', function () {
    beforeEach(fn () => TicketPlugin::get()->linkedConversationView(TicketPlugin::LINKED_VIEW_MODAL));

    it('shows the conversation of the ticket it was escalated from', function () {
        $requester = User::factory()->create(['name' => 'Original Requester']);
        $escalated = Ticket::factory()->create();
        $original = Ticket::factory()->create([
            'panel' => 'test2',
            'submitter_id' => $requester->id,
            'linked_ticket_id' => $escalated->id,
        ]);

        TicketActivity::factory()->create([
            'ticket_id' => $original->id,
            'type' => ActivityType::Message,
            'sender' => ActivitySender::User,
            'user_id' => $requester->id,
            'content' => '<p>The rent looks wrong</p>',
        ]);

        Livewire::test(ViewTicket::class, ['record' => $escalated->id])
            ->assertActionVisible(ViewOriginalConversationAction::class)
            ->mountAction(ViewOriginalConversationAction::class)
            ->assertMountedActionModalSee(['Original Requester', 'The rent looks wrong']);
    });

    it('is hidden on a ticket that was not escalated from another', function () {
        $ticket = Ticket::factory()->create();

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden(ViewOriginalConversationAction::class);
    });

    it('is hidden from the team that escalated, who already have the original', function () {
        $escalated = Ticket::factory()->create(['panel' => 'test2', 'submitter_id' => auth()->id()]);
        Ticket::factory()->create(['linked_ticket_id' => $escalated->id]);

        Livewire::test(ViewTicket::class, ['record' => $escalated->id])
            ->assertActionHidden(ViewOriginalConversationAction::class);
    });
});

it('explains each detail inline when the panel asks for inline help', function () {
    TicketPlugin::get()->fieldHelp(TicketPlugin::FIELD_HELP_INLINE);

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.hints.turn'), escape: false)
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.hints.assignee'), escape: false)
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.field_help.label'));
});

it('explains every detail in one place when the panel asks for a summary', function () {
    TicketPlugin::get()->fieldHelp(TicketPlugin::FIELD_HELP_SUMMARY);

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.hints.turn'), escape: false)
        ->mountAction(TestAction::make('field-help')->schemaComponent('fieldHelp', schema: 'form'))
        ->assertMountedActionModalSee([
            __('padmission-tickets::tickets.resources.tickets.hints.turn'),
            __('padmission-tickets::tickets.resources.tickets.hints.assignee'),
            __('padmission-tickets::tickets.resources.tickets.hints.submitter'),
        ]);
});

it('labels the person who asked as requested by', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.submitter'))
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.contact'));
});

it('labels the person an escalation sent here came from as the contact at their organization', function (?string $organization, string $hint) {
    TicketPlugin::get()->fieldHelp(TicketPlugin::FIELD_HELP_INLINE);
    TicketPlugin::get()->describeTicketOriginUsing(fn (): ?string => $organization);
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalated = Ticket::factory()->create();
    Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalated->id]);

    Livewire::test(ViewTicket::class, ['record' => $escalated->id])
        ->call('closeLinked')
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.contact'))
        ->assertSee($hint, escape: false);
})->with([
    'with an organization' => ['Test Organization', 'The person at Test Organization you&#039;re talking with.'],
    'without one' => [null, 'The person you&#039;re talking with.'],
]);

it('explains each detail in a tooltip on its label by default, reachable by keyboard', function () {
    $ticket = Ticket::factory()->create();

    $html = Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.field_help.label'))
        ->html();

    $turnHelp = __('padmission-tickets::tickets.resources.tickets.hints.turn');

    expect($html)
        ->toMatch('/class="pad-ti-help-label" tabindex="0" aria-describedby="(pad-ti-help-\\w+)"[^>]*>'.preg_quote(__('padmission-tickets::tickets.resources.tickets.turn'), '/').'<\\/span><span id="\\1" class="fi-sr-only">'.preg_quote(e($turnHelp), '/').'/')
        ->not->toContain('fi-sc-icon fi-icon fi-size-md" xmlns');
});

it('offers a second send button that keeps the ticket waiting on support by default', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSeeHtml('keep-waiting-style="'.TicketPlugin::KEEP_WAITING_BUTTON.'"');

    TicketPlugin::get()->keepWaitingStyle(TicketPlugin::KEEP_WAITING_CHECKBOX);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSeeHtml('keep-waiting-style="'.TicketPlugin::KEEP_WAITING_CHECKBOX.'"');
});

it('shows the first of several roles and keeps the rest in a tooltip', function () {
    $submitter = User::factory()->create();

    TicketPlugin::get()->describeUsersUsing(fn (): array => ['Household Specialist', 'Inspector', 'Request Payments']);

    $ticket = Ticket::factory()->create(['submitter_id' => $submitter->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSeeHtml('Household Specialist <span class="pad-ti-more"')
        ->assertSeeHtml('aria-label="Household Specialist, Inspector, Request Payments">+2</span>');
});

it('shows the ticket number once, as a small reference under the heading', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSeeHtml('<span class="pad-ti-ticket-number">Ticket #'.$ticket->id.'</span>');
});

describe('Escalation on the original\'s page', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        Gate::policy(Ticket::class, TicketPolicy::class);
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('is shown only to people who may manage the original', function (bool $asRequester) {
        [$requester, $supporter] = User::factory()->count(2)->create();

        $this->modifyPlugin(function ($plugin) use ($supporter) {
            $plugin->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
        });

        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => $supporter->id]);
        $original = Ticket::factory()->open()->create(['submitter_id' => $requester->id, 'linked_ticket_id' => $escalation->id]);

        $this->actingAs($asRequester ? $requester : $supporter);

        $page = Livewire::test(ViewTicket::class, ['record' => $original->id]);

        if ($asRequester) {
            $page->assertDontSee('Escalated to Platform Support')
                ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
                ->assertActionHidden('show-linked');

            return;
        }

        $page->assertSee('Escalated to Platform Support')
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'))
            ->assertActionVisible('show-linked');
    })->with([
        'the requester' => [true],
        'a supporter' => [false],
    ]);
});

it('tells the chat whether the viewer may reply', function (bool $mayReply) {
    Gate::policy(Ticket::class, $mayReply ? TestTicketPolicy::class : ManagesButMayNotReplyPolicy::class);

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSeeHtml('can-reply="'.($mayReply ? 'true' : 'false').'"');
})->with([
    'may reply' => [true],
    'may not reply' => [false],
]);

function contextMessage(Ticket $ticket, ActivitySender $sender, ?int $userId): TicketActivity
{
    return TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => $sender,
        'user_id' => $userId,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array{0: Ticket, 1: Ticket}
 */
function contextEscalatedOriginal(array $attributes = [], ?int $ownerId = null): array
{
    $escalation = Ticket::factory()->open()->create([
        'panel' => 'test2',
        'source_panel' => 'test',
        'submitter_id' => $ownerId ?? auth()->id(),
        'turn' => Turn::Supporter,
        ...$attributes,
    ]);

    $original = Ticket::factory()->open()->create([
        'linked_ticket_id' => $escalation->id,
        'turn' => Turn::Supporter,
        'submitter_id' => User::factory()->create(['name' => 'Aisha Brooks'])->id,
    ]);

    return [$escalation, $original];
}

describe('Conversation headings', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('names the requester on an original, with the reply box addressed to them', function () {
        $ticket = Ticket::factory()->open()->create(['submitter_id' => User::factory()->create(['name' => 'Aisha Brooks'])->id]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.linked_view.conversation_with', ['name' => 'Aisha Brooks']))
            ->assertSeeHtml('placeholder="Reply to Aisha Brooks…"');
    });

    it('calls the viewer\'s own ticket their own, with the default reply box', function () {
        $ticket = Ticket::factory()->open()->create(['submitter_id' => auth()->id()]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee(__('padmission-tickets::tickets.linked_view.conversation_own'))
            ->assertSeeHtml('placeholder="'.e(__('padmission-tickets::chat.chat.placeholder')).'"');
    });

    it('names the other team on the escalation and says who never sees it', function (int $originals, string $description) {
        $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => auth()->id()]);
        TicketActivity::factory()->create(['ticket_id' => $escalation->id, 'type' => ActivityType::OriginalAdded, 'sender' => ActivitySender::System]);

        foreach (array_slice(['Aisha Brooks', 'Felix Moreno'], 0, $originals) as $name) {
            Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id, 'submitter_id' => User::factory()->create(['name' => $name])->id]);
        }

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee('Conversation with Platform Support')
            ->assertSee($description)
            ->assertSeeHtml('placeholder="Reply to Platform Support…"');
    })->with([
        'one original' => [1, "About Aisha Brooks's ticket. Aisha Brooks never sees this conversation."],
        'two originals' => [2, "About Aisha Brooks's and Felix Moreno's tickets. The requesters never see this conversation."],
        'none left' => [0, 'Not linked to any ticket.'],
    ]);

    it('addresses the reply box to the contact on an escalation sent here', function () {
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

        $escalation = Ticket::factory()->open()->create(['submitter_id' => User::factory()->create(['name' => 'Test Admin'])->id]);
        Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee('Conversation with Test Admin')
            ->assertSeeHtml('placeholder="Reply to Test Admin…"');
    });
});

describe('Escalation status line', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('asks the owner to pass on the other team\'s reply', function () {
        [$escalation, $original] = contextEscalatedOriginal(['turn' => Turn::User]);
        contextMessage($escalation, ActivitySender::User, auth()->id());
        contextMessage($escalation, ActivitySender::Supporter, null);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->call('closeLinked')
            ->assertSee('Platform Support replied on the escalation. Read it, then answer Platform Support there or pass the answer on to Aisha Brooks here.')
            ->assertSeeHtml('wire:click="showLinked('.$escalation->id.')"')
            ->assertSee("Read Platform Support's reply")
            ->assertSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $escalation, 'linked' => $original->id])).'"');
    });

    it('names the colleague the other team replied to', function () {
        $colleague = User::factory()->create(['name' => 'Maria Lopez']);
        [$escalation, $original] = contextEscalatedOriginal(['turn' => Turn::User], $colleague->id);
        contextMessage($escalation, ActivitySender::User, $colleague->id);
        contextMessage($escalation, ActivitySender::Supporter, null);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSee('Platform Support replied to Maria Lopez on the escalation.')
            ->assertDontSee("Read Platform Support's reply");
    });

    it('says who owes the next message on the escalation', function (Turn $turn, bool $owner, string $text, bool $offersOpen) {
        $colleague = User::factory()->create(['name' => 'Maria Lopez']);
        [$escalation, $original] = contextEscalatedOriginal(['turn' => $turn], $owner ? null : $colleague->id);
        contextMessage($escalation, ActivitySender::User, $escalation->submitter_id);

        $html = Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSee($text)
            ->html();

        // The Escalation box always links to it; the status line only when the escalation waits on its owner.
        $link = 'href="'.e(ViewTicket::getUrl(['record' => $escalation, 'linked' => $original->id])).'"';
        expect(substr_count($html, $link))->toBe($offersOpen ? 2 : 1);
    })->with([
        'the team, asked by the viewer' => [Turn::Supporter, true, 'You asked Platform Support about this. Platform Support owes the next reply there.', false],
        'the team, asked by a colleague' => [Turn::Supporter, false, 'Maria Lopez asked Platform Support about this. Platform Support owes the next reply there.', false],
        'the viewer' => [Turn::User, true, 'Platform Support is waiting on you on the escalation.', true],
        'a colleague' => [Turn::User, false, 'Platform Support is waiting on Maria Lopez on the escalation.', true],
    ]);

    it('adds that the requester is still waiting for a reply', function () {
        [$escalation, $original] = contextEscalatedOriginal();
        contextMessage($escalation, ActivitySender::User, auth()->id());
        contextMessage($original, ActivitySender::Supporter, auth()->id());
        contextMessage($original, ActivitySender::User, $original->submitter_id);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSee('Platform Support owes the next reply there. Aisha Brooks hasn&#039;t had a reply since their last message.', escape: false);

        contextMessage($original, ActivitySender::Supporter, auth()->id());

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertDontSee('hasn&#039;t had a reply', escape: false);
    });

    it('says who closed the escalation and that this ticket stays open', function (bool $knownCloser) {
        $closer = User::factory()->create(['name' => 'Kevin McKee']);
        [$escalation, $original] = contextEscalatedOriginal();
        $escalation->update(['closed_at' => now()->subHour(), 'closed_by' => $knownCloser ? $closer->id : null]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSee($knownCloser
                ? 'Kevin McKee closed the escalation 1 hour ago. This ticket stays open until your team closes it.'
                : 'The escalation was closed 1 hour ago. This ticket stays open until your team closes it.');
    })->with([
        'by someone known' => [true],
        'by nobody recorded' => [false],
    ]);

    it('is not shown on a closed original, an original without an escalation, or to the requester', function (string $case) {
        [$escalation, $original] = contextEscalatedOriginal();

        match ($case) {
            'closed' => $original->update(['closed_at' => now()]),
            'deleted escalation' => $escalation->delete(),
            'requester' => $this->actingAs($original->submitter),
        };

        Gate::policy(Ticket::class, TicketPolicy::class);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertDontSeeHtml('pad-ti-escalation-status');
    })->with(['closed', 'deleted escalation', 'requester']);
});

describe('Escalation box on an original', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
    });

    it('says who handles the escalation and links to it', function (bool $owner, string $text) {
        [$escalation, $original] = contextEscalatedOriginal([], $owner ? null : User::factory()->create(['name' => 'Maria Lopez'])->id);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSee($text)
            ->assertActionVisible(TestAction::make('open-escalation')->schemaComponent('escalationActions', schema: 'form'))
            ->assertDontSee('View escalation');
    })->with([
        'the viewer' => [true, 'Escalated to Platform Support. You handle the conversation with Platform Support.'],
        'a colleague' => [false, 'Escalated to Platform Support. Maria Lopez handles the conversation with Platform Support.'],
    ]);

    it('offers no link to a colleague who cannot open the escalation', function () {
        Gate::policy(Ticket::class, OnlyOwnEscalationsPolicy::class);
        [$escalation, $original] = contextEscalatedOriginal(['turn' => Turn::User], User::factory()->create()->id);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertDontSee(__('padmission-tickets::tickets.linked_view.open_escalation'))
            ->assertDontSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $escalation, 'linked' => $original->id])).'"')
            ->assertActionHidden('show-linked');
    });

    it('says a closed escalation is closed and offers to escalate again', function () {
        [$escalation, $original] = contextEscalatedOriginal();
        $escalation->update(['closed_at' => now()]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertSee('The escalation to Platform Support is closed.')
            ->assertSee('Escalate to Platform Support again')
            ->assertSee(__('padmission-tickets::tickets.actions.add_to_escalation.help_to', ['team' => 'Platform Support']))
            ->assertDontSee(__('padmission-tickets::tickets.linked_view.open_escalation'));
    });
});

describe('Escalation page on the side that escalated', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test2')->supportTeamName('Platform Support');
        TicketPlugin::get()->fieldHelp(TicketPlugin::FIELD_HELP_INLINE);
    });

    it('explains the fields in terms of the other team', function () {
        [$escalation] = contextEscalatedOriginal();

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee(__('padmission-tickets::tickets.resources.tickets.handled_by'))
            ->assertSee('The person on your team who talks with Platform Support here.')
            ->assertSee('Platform Support&#039;s status for this escalation.', escape: false)
            ->assertSee('Your team&#039;s conversation with Platform Support. Answer the requesters on their own tickets.', escape: false);
    });

    it('says who closed it and how to ask again', function () {
        [$escalation] = contextEscalatedOriginal();
        $escalation->update(['closed_at' => now(), 'closed_by' => User::factory()->create(['name' => 'Kevin McKee'])->id]);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee('Kevin McKee closed this escalation. To ask Platform Support again, use Escalate again on the original ticket.');
    });

    it('links each original card to the original with the escalation beside it', function () {
        [$escalation, $original] = contextEscalatedOriginal();

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $original, 'linked' => $escalation->id])).'"');
    });
});

it('links each original card on an escalation sent here to this page with the original beside it', function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalation = Ticket::factory()->open()->create();
    $original = Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->call('closeLinked')
        ->assertSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $escalation, 'linked' => $original->id])).'"');
});

it('reads who owes the next message again after a message is sent', function () {
    (new TicketStatusSeeder)->run();

    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => auth()->id(),
        'turn' => Turn::Supporter,
        'submitter_id' => User::factory()->create(['name' => 'Aisha Brooks'])->id,
    ]);

    $page = Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('You owe Aisha Brooks the next reply.');

    $ticket->update(['turn' => Turn::User]);

    $page->dispatch('message-sent');

    expect(json_encode($page->effects['partials'] ?? []))
        ->toContain(__('padmission-tickets::tickets.resources.tickets.waiting_on.requester'))
        ->not->toContain('You owe Aisha Brooks the next reply.');
});

it('reads who owes the next message again after an action changes the ticket', function () {
    (new TicketStatusSeeder)->run();

    $colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => $colleague->id,
        'turn' => Turn::Supporter,
        'submitter_id' => User::factory()->create(['name' => 'Aisha Brooks'])->id,
    ]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('Maria Lopez owes Aisha Brooks the next reply.')
        ->callAction(TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form'), ['assignee_id' => auth()->id()])
        ->assertHasNoActionErrors()
        ->assertSee('You owe Aisha Brooks the next reply.');
});

class OnlyOwnEscalationsPolicy extends TestTicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->panel === 'test' || $ticket->submitter_id === $user->getKey();
    }
}

class ManagesButMayNotReplyPolicy extends TestTicketPolicy
{
    public function reply(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
