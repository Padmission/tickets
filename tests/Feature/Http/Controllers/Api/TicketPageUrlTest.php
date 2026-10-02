<?php

use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketUrlService;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    $this->login();
});

it('keeps the page a ticket was opened from only when it is a web, mail or relative address', function (string $url) {
    $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Rent', 'url' => $url])->assertSuccessful();

    expect(Ticket::query()->sole()->data['url'])->toBe($url);
})->with(['https://app.example.com/households/4', 'http://localhost/page', '/households/4?tab=rent', 'mailto:help@example.com']);

it('refuses a page address that would run script or load something else when followed', function (string $url) {
    $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Rent', 'url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    expect(Ticket::query()->exists())->toBeFalse();
})->with([
    'javascript:alert(document.domain)',
    ' JaVaScRiPt:alert(1)',
    "java\tscript:alert(1)",
    'vbscript:msgbox(1)',
    'data:text/html,<script>alert(1)</script>',
    'file:///etc/passwd',
    'another host, without a scheme' => '//evil.example/login',
    'another host, behind a backslash' => '/\\evil.example/login',
    'another host, behind two backslashes' => '\\\\evil.example/login',
]);

it('links a ticket saved before with an unsafe page address to the app instead', function () {
    $ticket = Ticket::factory()->open()->create(['data' => ['url' => 'javascript:alert(1)']]);

    expect(resolve(TicketUrlService::class)->getActionUrl($ticket))->toBe(url('/').'#ticket-'.$ticket->id);
});
