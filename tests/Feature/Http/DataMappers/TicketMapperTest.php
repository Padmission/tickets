<?php

use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Http\DataMappers\TicketMapper;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;

it('returns subjects stored HTML encoded as plain text', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Tom &amp; Jerry &lt;3 &quot;quoted&quot; it&#039;s',
    ]);

    expect(TicketMapper::map($ticket)['subject'])
        ->toBe('Tom & Jerry <3 "quoted" it\'s');
});

it('decodes HTML5 named entities in subjects', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'it&apos;s &lpar;fine&rpar;',
    ]);

    expect(TicketMapper::map($ticket)['subject'])
        ->toBe("it's (fine)");
});

it('returns subjects stored raw as plain text', function () {
    $ticket = Ticket::factory()->create([
        'subject' => '<img src=x onerror=alert(1)>',
    ]);

    expect(TicketMapper::map($ticket)['subject'])
        ->toBe('<img src=x onerror=alert(1)>');
});

it('returns the latest message preview as plain text', function () {
    $ticket = Ticket::factory()->create();

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'content' => '<p>Tom &amp; Jerry &lt;img src=x onerror=alert(1)&gt;</p>',
    ]);

    expect(TicketMapper::map($ticket->fresh())['latest_message'])
        ->toBe('Tom & Jerry <img src=x onerror=alert(1)>');
});

it('does not flag a closed ticket as needing the requester', function () {
    (new TicketStatusSeeder)->run();

    $waiting = Ticket::factory()->open()->create(['turn' => Turn::User]);
    $closed = Ticket::factory()->closed()->create(['turn' => Turn::User]);

    expect(TicketMapper::map($waiting)['needs_attention'])->toBeTrue()
        ->and(TicketMapper::map($closed)['needs_attention'])->toBeFalse();
});
