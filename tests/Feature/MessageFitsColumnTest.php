<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\OpenTicketForContactAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\StartTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\TicketStarter;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * Cleaning writes each quote out as &quot;, so 16,000 quotes become 96,007
 * bytes, more than a MySQL TEXT column holds. SQLite stores them anyway, so
 * the tests ask that nothing that long is written at all.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    $this->tooLong = '<p>'.str_repeat('"', 16000).'</p>';
    $this->fits = '<p>'.str_repeat('"', 10000).'</p>';
});

function longestStoredMessage(): int
{
    return (int) TicketActivity::query()->where('type', ActivityType::Message)->get()->max(fn (TicketActivity $activity): int => strlen((string) $activity->getRawOriginal('content')));
}

it('refuses a New ticket message too long for its column once cleaned, leaving no ticket behind', function (string $kind) {
    $this->login();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $requester = User::factory()->create();

    $start = fn (string $message) => Livewire::test(ListTickets::class)->callAction(TestAction::make(StartTicketAction::class), [
        'kind' => $kind,
        'requester_id' => $kind === StartTicketAction::ORGANIZATION ? $requester->id : null,
        'assign' => 'me',
        'subject' => 'Quotes',
        'message' => $message,
    ]);

    $start($this->tooLong)->assertHasActionErrors(['message']);

    expect(Ticket::query()->where('subject', 'Quotes')->exists())->toBeFalse()
        ->and(longestStoredMessage())->toBeLessThanOrEqual(65535);

    $start($this->fits)->assertHasNoActionErrors();

    expect(Ticket::query()->where('subject', 'Quotes')->exists())->toBeTrue();
})->with([StartTicketAction::ORGANIZATION, StartTicketAction::ESCALATION]);

it('refuses an Open for contact message too long for its column once cleaned', function () {
    $staff = User::factory()->create();
    $contact = User::factory()->create();
    TicketPlugin::get('test')->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test')->allSupportersQuery(fn () => User::query()->whereKey($contact->id));
    TicketPlugin::get('test2')->startsTickets()->allSupportersQuery(fn () => User::query()->whereKey($staff->id));
    Filament::setCurrentPanel('test2');
    $this->login($staff);

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make(OpenTicketForContactAction::class), [
            'contact_id' => $contact->id,
            'subject' => 'Quotes',
            'message' => $this->tooLong,
        ])
        ->assertHasActionErrors(['message']);

    expect(Ticket::query()->where('subject', 'Quotes')->exists())->toBeFalse();
});

it('refuses an escalation or requester message too long for its column once cleaned, escalating nothing', function (string $field) {
    $this->login();
    TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);
    $original = Ticket::factory()->open()->create(['submitter_id' => User::factory()->create()->id, 'turn' => Turn::Supporter]);

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->mountAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'))
        ->fillForm([
            'subject' => 'Quotes',
            'message' => $field === 'message' ? $this->tooLong : '<p>Please check</p>',
            'notify_requester' => true,
            'requester_message' => $field === 'requester_message' ? $this->tooLong : '<p>On it</p>',
        ])
        ->callMountedAction()
        ->assertHasActionErrors([$field]);

    expect(Ticket::query()->where('subject', 'Quotes')->exists())->toBeFalse()
        ->and($original->refresh()->linked_ticket_id)->toBeNull()
        ->and(longestStoredMessage())->toBeLessThanOrEqual(65535);
})->with(['message', 'requester_message']);

it('refuses it in the service too, for a caller without the form, leaving no ticket behind', function () {
    $this->login();

    expect(fn () => resolve(TicketStarter::class)->openFor(User::factory()->create(), 'Quotes', $this->tooLong))
        ->toThrow(ValidationException::class);

    expect(Ticket::query()->where('subject', 'Quotes')->exists())->toBeFalse();
});
