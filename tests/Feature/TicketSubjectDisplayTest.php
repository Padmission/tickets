<?php

use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Livewire\CopilotTicketPanel;
use Padmission\Tickets\Models\Ticket;

/*
 * Tickets opened from the chat widget before the fix were stored with their
 * subject HTML-escaped, such as #64's "Household 14&#039;s recert rent".
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();

    $this->user = $this->login();
    $this->typed = 'Household 14\'s rent & "utility" < last year';
    $this->ticket = Ticket::factory()->open()->create([
        'submitter_id' => $this->user->id,
        'subject' => 'Household 14&#039;s rent &amp; &quot;utility&quot; &lt; last year',
    ]);
});

it('reads a subject stored escaped as the text that was typed', function () {
    expect($this->ticket->refresh()->subject)->toBe($this->typed);
});

it('gives the chat widget the typed subject from its ticket list and its messages', function () {
    $this->getJson(route('padmission-tickets::api.index'))
        ->assertOk()
        ->assertJsonPath('tickets.0.subject', $this->typed);

    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))
        ->assertOk()
        ->assertJsonPath('ticket.subject', $this->typed);
});

it('escapes the subject once on the ticket page, in its heading and its title', function () {
    $page = Livewire::test(ViewTicket::class, ['record' => $this->ticket->id]);

    expect($page->instance()->getTitle())->toBe($this->typed)
        ->and($page->instance()->getHeading())->toBe($this->typed)
        ->and($page->html())->toContain(e($this->typed))
        ->not->toContain('&amp;#039;')
        ->not->toContain('&amp;amp;');
});

it('escapes the subject once in the assistant\'s tickets pane', function () {
    Livewire::test(CopilotTicketPanel::class)
        ->assertSeeHtml(e($this->typed))
        ->assertDontSeeHtml('&amp;#039;');

    Livewire::test(CopilotTicketPanel::class, ['initialTicketId' => $this->ticket->id])
        ->assertSeeHtml(e($this->typed))
        ->assertDontSeeHtml('&amp;#039;');
});

it('never renders a subject as markup on the ticket page, whether stored as text or escaped', function (string $stored) {
    $this->ticket->forceFill(['subject' => $stored])->save();

    expect(Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])->html())
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
})->with([
    'as text' => ['<img src=x onerror=alert(1)>'],
    'escaped' => ['&lt;img src=x onerror=alert(1)&gt;'],
]);
