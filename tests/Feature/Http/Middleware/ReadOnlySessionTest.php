<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Http\Middleware\ReadOnlySession;
use Padmission\Tickets\Models\Ticket;

beforeEach(function () {
    (new TicketStatusSeeder)->run();

    $this->user = $this->login();
    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->user->id]);
});

dataset('reads', [
    'the ticket list' => [fn () => route('padmission-tickets::api.index')],
    'the unread count' => [fn () => route('padmission-tickets::api.unread-count')],
    'the messages' => [fn () => route('padmission-tickets::api.messages.index', ['ticket' => test()->ticket])],
]);

function storedSession(): array
{
    $session = app('session')->driver();
    $stored = $session->getHandler()->read($session->getId());

    return $stored === '' ? [] : unserialize($stored);
}

it('makes only the read endpoints read-only', function () {
    $middleware = fn (string $name): array => app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName("padmission-tickets::api.{$name}"));

    foreach (['index', 'unread-count', 'messages.index'] as $read) {
        expect($middleware($read))->toContain(ReadOnlySession::class);
    }

    foreach (['store', 'messages.store', 'mark-seen', 'attachment-url', 'temporary-attachment-url'] as $write) {
        expect($middleware($write))->not->toContain(ReadOnlySession::class);
    }
});

it('never writes the session from a read', function (Closure $url) {
    $this->withSession(['seen' => 'before'])->getJson($url())->assertOk();

    expect(storedSession())->toBe([]);
})->with('reads');

/*
 * A page request claims a flashed notification while the chat's read is
 * running. The read loaded the session before the claim, so saving it would
 * write the notification back for the next page to show again.
 */
it('leaves a notification claimed during a read claimed', function (Closure $url) {
    $claimed = false;

    DB::listen(function () use (&$claimed): void {
        if ($claimed) {
            return;
        }

        $claimed = true;
        $session = app('session')->driver();
        $session->getHandler()->write($session->getId(), serialize(['_token' => $session->token()]));
    });

    $this->withSession(['filament.notifications' => ['toast' => ['title' => 'Added to the escalation']]])
        ->getJson($url())
        ->assertOk();

    expect($claimed)->toBeTrue()
        ->and(storedSession())->not->toHaveKey('filament.notifications')
        ->and(storedSession())->not->toHaveKey('filament');
})->with('reads');

it('still saves the session from a write, including one right after a read', function () {
    $this->withSession(['seen' => 'before'])->getJson(route('padmission-tickets::api.unread-count'))->assertOk();

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])->assertOk();

    expect(storedSession())->toHaveKey('seen', 'before');
});
