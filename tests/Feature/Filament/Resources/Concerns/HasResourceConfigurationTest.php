<?php

use Padmission\Tickets\Filament\Resources\Concerns\HasResourceConfiguration;
use Padmission\Tickets\Filament\Resources\Priorities\PriorityResource;
use Padmission\Tickets\Filament\Resources\Statuses\StatusResource;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;

dataset('resources', $resources = [
    TicketResource::class,
    StatusResource::class,
    PriorityResource::class,
]);

afterEach(function () use ($resources) {
    foreach ($resources as $resourceClass) {
        $resourceClass::configure(
            modelLabel: null,
            pluralModelLabel: null,
            navigationGroup: null,
            navigationIcon: null,
        );
    }
});

test('resources can be configured', function (string $resourceClass) {
    /**
     * @var HasResourceConfiguration $resourceClass
     */
    expect($resourceClass::getModelLabel())->not->toBe('label')
        ->and($resourceClass::getPluralModelLabel())->not->toBe('plural label')
        ->and($resourceClass::getNavigationGroup())->not->toBe('group')
        ->and($resourceClass::getNavigationIcon())->not->toBe('icon');

    $resourceClass::configure(
        modelLabel: 'label',
        pluralModelLabel: 'plural label',
        navigationGroup: 'group',
        navigationIcon: 'icon'
    );

    expect($resourceClass::getModelLabel())->toBe('label')
        ->and($resourceClass::getPluralModelLabel())->toBe('plural label')
        ->and($resourceClass::getNavigationGroup())->toBe('group')
        ->and($resourceClass::getNavigationIcon())->toBe('icon');
})
    ->with('resources');

test('resources can be configured via closure', function (string $resourceClass) {
    /**
     * @var HasResourceConfiguration $resourceClass
     */
    $resourceClass::configure(
        modelLabel: fn () => 'label',
        pluralModelLabel: fn () => 'plural label',
        navigationGroup: fn () => 'group',
        navigationIcon: fn () => 'icon'
    );

    expect($resourceClass::getModelLabel())->toBe('label')
        ->and($resourceClass::getPluralModelLabel())->toBe('plural label')
        ->and($resourceClass::getNavigationGroup())->toBe('group')
        ->and($resourceClass::getNavigationIcon())->toBe('icon');
})
    ->with('resources');
