<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Http\Middleware\AuthenticateChatSession;
use Padmission\Tickets\Models\Ticket;

beforeEach(function () {
    (new TicketStatusSeeder)->run();

    $this->user = $this->login();
    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->user->id]);
});

// The routes read the config as they load, as a host's boot or route:cache would.
function reloadTicketApiRoutes(): void
{
    require dirname(__DIR__, 4).'/routes/api.php';

    app('router')->getRoutes()->refreshNameLookups();
}

it('checks the session on every ticket API route by default', function () {
    foreach (['index', 'unread-count', 'messages.index', 'store', 'messages.store', 'mark-seen', 'attachment-url', 'temporary-attachment-url'] as $name) {
        expect(app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName("padmission-tickets::api.{$name}")))
            ->toContain(AuthenticateChatSession::class);
    }
});

it('refuses a session a password change ended', function () {
    $this->withSession(['password_hash_web' => 'from-before-the-password-changed'])
        ->getJson(route('padmission-tickets::api.index'))
        ->assertUnauthorized();

    $this->withSession(['password_hash_web' => 'from-before-the-password-changed'])
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Still here</p>'])
        ->assertUnauthorized();

    expect($this->ticket->ticketActivities()->where('content', 'like', '%Still here%')->exists())->toBeFalse();
});

it('still serves someone signed in whose session is current', function () {
    $this->getJson(route('padmission-tickets::api.index'))->assertOk();
    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Hi</p>'])->assertOk();
    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))->assertOk();
});

it('runs the host\'s own middleware on the ticket API, such as its check that a user is still active', function () {
    config()->set('padmission-tickets.api.middleware', [AuthenticateChatSession::class, RefusesDeactivatedUsers::class]);
    reloadTicketApiRoutes();

    $this->getJson(route('padmission-tickets::api.index'))->assertOk();

    $this->user->update(['name' => 'Deactivated']);

    $this->getJson(route('padmission-tickets::api.index'))->assertForbidden();
    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), ['content' => '<p>Still here</p>'])->assertForbidden();

    expect($this->ticket->ticketActivities()->where('content', 'like', '%Still here%')->exists())->toBeFalse();
});

class RefusesDeactivatedUsers
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_if($request->user()?->name === 'Deactivated', 403);

        return $next($request);
    }
}
