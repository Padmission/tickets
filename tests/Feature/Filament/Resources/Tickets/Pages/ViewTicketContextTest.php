<?php

use Filament\Actions\Testing\TestAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ViewOriginalConversationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
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

    $ticket = Ticket::factory()->create(['panel' => 'test2', 'assignee_id' => User::factory()->create()->id]);

    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($ticket->assignee_id));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('Platform Support')
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.unassigned'));
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
        $escalated = Ticket::factory()->create(['panel' => 'test2']);
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
            __('padmission-tickets::tickets.resources.tickets.hints.escalated_by'),
        ]);
});

it('labels the person who asked as requested by', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.submitter'))
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.escalated_by'));
});

it('labels the person who escalated a ticket here as escalated by', function () {
    TicketPlugin::get()->fieldHelp(TicketPlugin::FIELD_HELP_INLINE);
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalated = Ticket::factory()->create();
    Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalated->id]);

    Livewire::test(ViewTicket::class, ['record' => $escalated->id])
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.escalated_by'))
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.hints.escalated_by'), escape: false);
});

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
