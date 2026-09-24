<?php

use Padmission\Tickets\Database\Seeders\TicketDispositionSeeder;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;

dataset('lookup seeders', [
    'statuses' => [TicketStatusSeeder::class, TicketStatus::class, 3],
    'priorities' => [TicketPrioritySeeder::class, TicketPriority::class, 3],
    'dispositions' => [TicketDispositionSeeder::class, TicketDisposition::class, 5],
]);

it('seeds a panel that has no rows yet while another panel already has some', function (string $seeder, string $model, int $defaults) {
    $model::factory()->create(['panel' => 'test']);

    (new $seeder)->run();

    expect($model::withoutGlobalScopes()->where('panel', 'test')->count())->toBe(1)
        ->and($model::withoutGlobalScopes()->where('panel', 'test2')->count())->toBe($defaults)
        ->and($model::withoutGlobalScopes()->where('panel', 'test3')->count())->toBe($defaults);
})->with('lookup seeders');

it('does not duplicate rows when run again', function (string $seeder, string $model, int $defaults) {
    (new $seeder)->run();
    (new $seeder)->run();

    expect($model::withoutGlobalScopes()->count())->toBe($defaults * 3);
})->with('lookup seeders');

it('leaves the current panel as it found it', function (string $seeder) {
    (new $seeder)->run();

    expect(filament()->getCurrentPanel()->getId())->toBe('test');
})->with('lookup seeders');
