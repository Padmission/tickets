<?php

use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->login();
});

function originalWithMessage(Ticket $escalation, string $message): Ticket
{
    $requester = User::factory()->create(['name' => 'Rita '.md5($message)]);
    $original = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id, 'submitter_id' => $requester->id]);

    TicketActivity::factory()->create([
        'ticket_id' => $original->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'user_id' => $requester->id,
        'content' => "<p>{$message}</p>",
    ]);

    return $original;
}

describe('beside the chat', function () {
    beforeEach(function () {
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    });

    it('shows an original beside the reply box and says where the reply goes', function () {
        TicketPlugin::get()->describeTicketOriginUsing(fn (): string => 'Acme Housing');

        $escalation = Ticket::factory()->open()->create();
        $original = originalWithMessage($escalation, 'The rent looks wrong');
        $requester = 'Rita '.md5('The rent looks wrong');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertActionHasLabel('show-linked', 'Show linked ticket')
            ->assertDontSee('The rent looks wrong')
            ->callAction('show-linked')
            ->assertSet('linkedTicketId', $original->id)
            ->assertSee(['The rent looks wrong', 'Rita '.md5('The rent looks wrong')])
            ->assertSee('Reply on the escalation with Acme Housing')
            ->assertSee("{$requester}'s original ticket")
            ->assertSee(__('padmission-tickets::tickets.linked_view.read_only'))
            ->assertDontSeeHtml('pad-ti-linked__close')
            ->assertDontSeeHtml('pad-ti-transcript__title')
            ->assertActionHasLabel('show-linked', 'Hide linked ticket');
    });

    it('switches between several originals and closes again', function () {
        $escalation = Ticket::factory()->open()->create();
        $first = originalWithMessage($escalation, 'First original message');
        $second = originalWithMessage($escalation, 'Second original message');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertActionHasLabel('show-linked', 'Show linked ticket')
            ->call('showLinked', $second->id)
            ->assertSeeHtml('<span class="pad-ti-linked__tab-number">· #'.$first->id.'</span>')
            ->assertSeeHtml('<span class="pad-ti-linked__tab-number">· #'.$second->id.'</span>')
            ->assertSeeHtml("content: '{$second->subject}'")
            ->assertSee('Second original message')
            ->assertDontSee('First original message')
            ->call('showLinked', $first->id)
            ->assertSee('First original message')
            ->call('closeLinked')
            ->assertSet('linkedTicketId', null)
            ->assertDontSee('First original message');
    });

    it('falls back to naming the escalation alone when it has no organization', function () {
        $escalation = Ticket::factory()->open()->create();
        originalWithMessage($escalation, 'No organization');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertSee('Reply on this escalation');
    });

    it('ignores a ticket that is not linked to this one', function () {
        $escalation = Ticket::factory()->open()->create();
        originalWithMessage($escalation, 'Linked');
        $stranger = Ticket::factory()->create(['panel' => 'test2']);

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->call('showLinked', $stranger->id)
            ->assertSet('linkedTicketId', null);
    });

    it('shows the escalation thread beside an original', function () {
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

        $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
        TicketActivity::factory()->create([
            'ticket_id' => $escalation->id,
            'type' => ActivityType::Message,
            'sender' => ActivitySender::Supporter,
            'content' => '<p>Reply from the escalation team</p>',
        ]);
        $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

        TicketPlugin::get('test2')->supportTeamName('Platform Support');
        $original->update(['submitter_id' => User::factory()->create(['name' => 'Rita Requester'])->id]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->assertActionHasLabel('show-linked', 'Show linked ticket')
            ->callAction('show-linked')
            ->assertSee('Reply from the escalation team')
            ->assertSee('Escalation to Platform Support')
            ->assertSee('Reply on Rita Requester\'s original ticket')
            ->assertActionHasLabel('show-linked', 'Hide linked ticket');
    });
});

it('offers a drawer instead when the panel asks for one', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    TicketPlugin::get()->linkedConversationView(TicketPlugin::LINKED_VIEW_DRAWER);

    $escalation = Ticket::factory()->open()->create();
    $original = originalWithMessage($escalation, 'Drawer message');

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->assertActionHasLabel('show-linked', 'Show linked ticket')
        ->callAction('show-linked')
        ->assertSee('Drawer message')
        ->assertSeeHtml('pad-ti-linked--drawer');
});

it('has nothing to show on a ticket without linked tickets', function () {
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden('show-linked');
});
