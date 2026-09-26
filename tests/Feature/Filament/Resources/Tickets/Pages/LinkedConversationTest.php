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
        $escalation = Ticket::factory()->open()->create();
        $original = originalWithMessage($escalation, 'The rent looks wrong');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertActionHasLabel('show-linked', __('padmission-tickets::tickets.linked_view.show_beside', ['id' => $original->id]))
            ->assertDontSee('The rent looks wrong')
            ->callAction('show-linked')
            ->assertSet('linkedTicketId', $original->id)
            ->assertSee(['The rent looks wrong', 'Rita '.md5('The rent looks wrong')])
            ->assertSee(__('padmission-tickets::tickets.linked_view.replying_on', ['id' => $escalation->id]))
            ->assertSee(__('padmission-tickets::tickets.linked_view.read_only'))
            ->assertDontSeeHtml('pad-ti-linked__close')
            ->assertDontSeeHtml('pad-ti-transcript__title')
            ->assertActionHasLabel('show-linked', __('padmission-tickets::tickets.linked_view.hide', ['id' => $original->id]));
    });

    it('switches between several originals and closes again', function () {
        $escalation = Ticket::factory()->open()->create();
        $first = originalWithMessage($escalation, 'First original message');
        $second = originalWithMessage($escalation, 'Second original message');

        Livewire::test(ViewTicket::class, ['record' => $escalation->id])
            ->assertActionHasLabel('show-linked', __('padmission-tickets::tickets.linked_view.show_beside_many', ['count' => 2]))
            ->call('showLinked', $second->id)
            ->assertSee('Second original message')
            ->assertDontSee('First original message')
            ->call('showLinked', $first->id)
            ->assertSee('First original message')
            ->call('closeLinked')
            ->assertSet('linkedTicketId', null)
            ->assertDontSee('First original message');
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

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->callAction('show-linked')
            ->assertSee('Reply from the escalation team')
            ->assertSee(__('padmission-tickets::tickets.linked_view.replying_on', ['id' => $original->id]));
    });
});

it('offers a drawer instead when the panel asks for one', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    TicketPlugin::get()->linkedConversationView(TicketPlugin::LINKED_VIEW_DRAWER);

    $escalation = Ticket::factory()->open()->create();
    $original = originalWithMessage($escalation, 'Drawer message');

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->assertActionHasLabel('show-linked', __('padmission-tickets::tickets.linked_view.open', ['id' => $original->id]))
        ->callAction('show-linked')
        ->assertSee('Drawer message')
        ->assertSeeHtml('pad-ti-linked--drawer');
});

it('has nothing to show on a ticket without linked tickets', function () {
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden('show-linked');
});
