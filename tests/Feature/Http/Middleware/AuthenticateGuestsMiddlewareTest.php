<?php

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Padmission\Tickets\Http\Middleware\AuthenticateGuests;
use Padmission\Tickets\Tests\User;

it('authenticates a user when session key exists', function () {
    $user = User::factory()->create();

    $request = Request::create('/test', 'GET');
    $session = new Store('test-session', new ArraySessionHandler(1));
    $session->put('padmission-tickets::user_key', $user->getKey());

    $request->setLaravelSession($session);

    expect(auth()->id())->toBeNull();

    $middleware = new AuthenticateGuests;
    $middleware->handle($request, function ($request) {});

    expect(auth()->id())->toBe($user->getKey());
});

it('does not authenticate when session key is missing', function () {
    User::factory()->create();

    $request = Request::create('/test', 'GET');
    $session = new Store('test-session', new ArraySessionHandler(1));

    $request->setLaravelSession($session);

    expect(auth()->id())->toBeNull();

    $middleware = new AuthenticateGuests;
    $middleware->handle($request, function ($request) {});

    expect(auth()->id())->toBeNull();
});

it('does not authenticate when session key is invalid', function () {
    User::factory()->create();

    $request = Request::create('/test', 'GET');
    $session = new Store('test-session', new ArraySessionHandler(1));
    $session->put('padmission-tickets::user_key', 99);

    $request->setLaravelSession($session);

    expect(auth()->id())->toBeNull();

    $middleware = new AuthenticateGuests;
    $middleware->handle($request, function ($request) {});

    expect(auth()->id())->toBeNull();
});
