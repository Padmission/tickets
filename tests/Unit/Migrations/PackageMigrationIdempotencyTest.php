<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Tests\Fixtures\Migrations\HostTicketSchema;
use Padmission\Tickets\Tests\Fixtures\Models\Tenant;

beforeEach(function () {
    $this->originalMigrationConnection = DB::getDefaultConnection();
    $mysqlDatabase = getenv('TICKETS_MIGRATION_TEST_DATABASE');
    if ($mysqlDatabase !== false) {
        // The optional MySQL run must use a disposable database, never a host DB.
        expect($mysqlDatabase)->toStartWith('tickets_migration_check_');
    }
    config()->set('database.connections.package_migration_test', $mysqlDatabase === false ? [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'foreign_key_constraints' => true,
    ] : [
        'driver' => 'mysql',
        'unix_socket' => '/tmp/mysql.sock',
        'database' => $mysqlDatabase,
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'modes' => ['STRICT_TRANS_TABLES', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION', 'ANSI_QUOTES'],
    ]);
    DB::setDefaultConnection('package_migration_test');
    Schema::clearResolvedInstance('db.schema');
    Schema::dropAllTables();
    ticketMigrationCreateHostDependencies();
    config()->set('padmission-tickets.tenancy.enabled', false);
});

afterEach(function () {
    Schema::dropAllTables();
    DB::setDefaultConnection($this->originalMigrationConnection);
    Schema::clearResolvedInstance('db.schema');
    DB::purge('package_migration_test');
});

function ticketMigrationCreateHostDependencies(): void
{
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
    });
    Schema::create('tenants', function (Blueprint $table): void {
        $table->id();
    });
}

function ticketMigrationRunAll(bool $twiceEach = false): void
{
    foreach (glob(__DIR__.'/../../../database/migrations/*.php') as $file) {
        $migration = require $file;
        $migration->up();
        if ($twiceEach) {
            $migration->up();
        }
    }
}

function ticketMigrationSchemaSnapshot(): array
{
    $schema = [];
    foreach (['ticket_dispositions', 'ticket_statuses', 'ticket_priorities', 'tickets', 'ticket_activities', 'ticket_attachments', 'ticket_user_states'] as $table) {
        $columns = collect(Schema::getColumns($table))->sortBy('name')->values()->all();
        // Names naturally differ after legacy renames and in host-owned copies.
        $indexes = collect(Schema::getIndexes($table))->map(fn (array $index): array => [
            'columns' => $index['columns'], 'unique' => $index['unique'], 'primary' => $index['primary'],
        ])->sortBy(fn (array $index): string => json_encode($index))->values()->all();
        $foreignKeys = collect(Schema::getForeignKeys($table))->map(fn (array $key): array => [
            'columns' => $key['columns'], 'table' => $key['foreign_table'],
            'references' => $key['foreign_columns'], 'delete' => $key['on_delete'], 'update' => $key['on_update'],
        ])->sortBy(fn (array $key): string => json_encode($key))->values()->all();
        $schema[$table] = compact('columns', 'indexes', 'foreignKeys');
    }

    return $schema;
}

function ticketMigrationFreshSchema(): array
{
    ticketMigrationRunAll();
    $schema = ticketMigrationSchemaSnapshot();
    Schema::dropAllTables();
    ticketMigrationCreateHostDependencies();

    return $schema;
}

function ticketMigrationSeedConversation(): array
{
    $user = DB::table('users')->insertGetId([]);
    $status = DB::table('ticket_statuses')->insertGetId(['panel' => 'test', 'display_name' => 'Open', 'color' => 'blue']);
    $priority = DB::table('ticket_priorities')->insertGetId(['panel' => 'test', 'display_name' => 'Normal', 'color' => 'blue']);
    $ticket = DB::table('tickets')->insertGetId(['panel' => 'test', 'subject' => 'Existing host ticket', 'status_id' => $status, 'priority_id' => $priority, 'turn' => 'supporter']);
    $activity = DB::table('ticket_activities')->insertGetId(['ticket_id' => $ticket, 'sender' => 'submitter', 'type' => 'message', 'created_at' => '2026-01-01 10:00:00']);

    return compact('user', 'ticket', 'activity');
}

it('runs the complete package migration sequence twice with the same final schema', function (bool $tenancy) {
    config()->set('padmission-tickets.tenancy.enabled', $tenancy);
    config()->set('padmission-tickets.tenancy.tenancy_model', Tenant::class);
    ticketMigrationRunAll();
    $schema = ticketMigrationSchemaSnapshot();
    ticketMigrationRunAll();

    expect(ticketMigrationSchemaSnapshot())->toBe($schema)
        ->and(Schema::hasTable('ticket_notifications'))->toBeFalse()
        ->and(Schema::hasTable('ticket_last_seen'))->toBeFalse();
})->with([false, true]);

it('runs each individual package migration twice consecutively', function () {
    $expected = ticketMigrationFreshSchema();
    ticketMigrationRunAll(twiceEach: true);

    expect(ticketMigrationSchemaSnapshot())->toBe($expected);
});

it('upgrades older host copies to the same schema as a fresh package install', function (string $stateTable, bool $withAdditions, bool $withLinked, bool $tenancy) {
    config()->set('padmission-tickets.tenancy.enabled', $tenancy);
    config()->set('padmission-tickets.tenancy.tenancy_model', Tenant::class);
    $expected = ticketMigrationFreshSchema();
    HostTicketSchema::create($stateTable, $withAdditions, $withLinked);
    ticketMigrationRunAll(twiceEach: true);
    ticketMigrationRunAll();

    expect(ticketMigrationSchemaSnapshot())->toBe($expected)
        ->and(Schema::hasTable('ticket_notifications'))->toBeFalse()
        ->and(Schema::hasTable('ticket_last_seen'))->toBeFalse();
})->with([
    'old notifications and missing linked column' => ['ticket_notifications', false, false],
    'intermediate last seen' => ['ticket_last_seen', false, true],
    'current user states' => ['ticket_user_states', false, true],
    'host already has new columns and differently named indexes' => ['ticket_user_states', true, true],
])->with([false, true]);

it('repairs existing columns whose indexes or foreign keys are missing', function () {
    $expected = ticketMigrationFreshSchema();
    HostTicketSchema::create('ticket_notifications', withLinked: false);
    Schema::rename('ticket_notifications', 'ticket_user_states');
    Schema::table('ticket_user_states', function (Blueprint $table): void {
        $table->foreignId('last_seen_activity_id')->nullable();
        $table->foreignId('last_notified_activity_id')->nullable();
    });
    Schema::table('tickets', function (Blueprint $table): void {
        $table->foreignId('linked_ticket_id')->nullable();
        $table->foreignId('duplicate_of_ticket_id')->nullable();
    });
    Schema::table('ticket_attachments', function (Blueprint $table): void {
        $table->unsignedBigInteger('created_by')->nullable();
    });
    ticketMigrationRunAll(twiceEach: true);

    expect(ticketMigrationSchemaSnapshot())->toBe($expected);
});

it('handles every combination of legacy and current notification state tables without losing rows', function (array $tables) {
    HostTicketSchema::create('ticket_notifications');
    Schema::drop('ticket_notifications');
    $conversation = ticketMigrationSeedConversation();
    foreach ($tables as $tableName) {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->index('user_id');
            $table->unique(['ticket_id', 'user_id']);
        });
        DB::table($tableName)->insert(['ticket_id' => $conversation['ticket'], 'user_id' => $conversation['user'], 'created_at' => '2026-01-01 09:00:00', 'updated_at' => '2026-01-01 11:00:00']);
    }
    foreach (['2025_12_21_213448_rename_ticket_notifications_to_ticket_last_seen.php', '2025_12_23_150556_rename_ticket_notifications_to_ticket_user_states.php'] as $file) {
        $migration = require __DIR__.'/../../../database/migrations/'.$file;
        $migration->up();
        $migration->up();
    }
    ticketMigrationRunAll();
    $schema = ticketMigrationSchemaSnapshot();
    ticketMigrationRunAll();

    expect(ticketMigrationSchemaSnapshot())->toBe($schema)
        ->and(DB::table('ticket_user_states')->count())->toBe($tables === [] ? 0 : 1);
    // If a newer table existed, each older table remains intact for reconciliation.
    if (in_array('ticket_user_states', $tables, true)) {
        foreach ($tables as $tableName) {
            expect(DB::table($tableName)->count())->toBe(1);
        }
    } elseif (in_array('ticket_notifications', $tables, true) && in_array('ticket_last_seen', $tables, true)) {
        expect(DB::table('ticket_notifications')->count())->toBe(1);
    }
})->with([
    'neither' => [[]],
    'notifications only' => [['ticket_notifications']],
    'last seen only' => [['ticket_last_seen']],
    'user states only' => [['ticket_user_states']],
    'notifications and last seen' => [['ticket_notifications', 'ticket_last_seen']],
    'notifications and user states' => [['ticket_notifications', 'ticket_user_states']],
    'last seen and user states' => [['ticket_last_seen', 'ticket_user_states']],
    'all three' => [['ticket_notifications', 'ticket_last_seen', 'ticket_user_states']],
]);

it('backfills legacy rows without overwriting existing pointers on repeat runs', function (string $stateTable) {
    HostTicketSchema::create($stateTable);
    $conversation = ticketMigrationSeedConversation();
    DB::table($stateTable)->insert(['ticket_id' => $conversation['ticket'], 'user_id' => $conversation['user'], 'created_at' => '2026-01-01 09:00:00', 'updated_at' => '2026-01-01 11:00:00']);
    ticketMigrationRunAll();
    $state = DB::table('ticket_user_states')->first();
    expect($state->last_seen_activity_id)->toBe($conversation['activity'])
        ->and($state->last_notified_activity_id)->toBe($conversation['activity']);
    ticketMigrationRunAll();
    expect(DB::table('ticket_user_states')->first())->toEqual($state);
})->with(['ticket_notifications', 'ticket_last_seen']);

it('backfills a missing pointer column while preserving an existing activity pointer', function (string $existingColumn) {
    HostTicketSchema::create('ticket_notifications');
    Schema::rename('ticket_notifications', 'ticket_user_states');
    Schema::table('ticket_user_states', function (Blueprint $table) use ($existingColumn): void {
        $table->foreignId($existingColumn)->nullable();
    });
    $conversation = ticketMigrationSeedConversation();
    DB::table('ticket_user_states')->insert(['ticket_id' => $conversation['ticket'], 'user_id' => $conversation['user'], $existingColumn => $conversation['activity'], 'created_at' => '2026-01-01 09:00:00', 'updated_at' => '2026-01-01 11:00:00']);
    ticketMigrationRunAll(twiceEach: true);

    $state = DB::table('ticket_user_states')->first();
    expect($state->last_seen_activity_id)->toBe($conversation['activity'])
        ->and($state->last_notified_activity_id)->toBe($conversation['activity']);
})->with(['last_seen_activity_id', 'last_notified_activity_id']);

it('skips an orphaned foreign key with a warning, preserves the rows and adds it after reconciliation', function (string $tableName, string $column, string $referencedTable) {
    HostTicketSchema::create('ticket_notifications', withLinked: false);
    Schema::rename('ticket_notifications', 'ticket_user_states');
    Schema::table('tickets', function (Blueprint $table): void {
        $table->foreignId('linked_ticket_id')->nullable();
        $table->foreignId('duplicate_of_ticket_id')->nullable();
    });
    Schema::table('ticket_user_states', function (Blueprint $table): void {
        $table->foreignId('last_seen_activity_id')->nullable();
        $table->foreignId('last_notified_activity_id')->nullable();
    });
    $conversation = ticketMigrationSeedConversation();
    $validReference = $referencedTable === 'tickets' ? $conversation['ticket'] : $conversation['activity'];

    // Count orphaned rows, including repeated references, but exclude valid and null values.
    foreach ([null, $validReference, 999999, 999999] as $reference) {
        if ($tableName === 'tickets') {
            $attributes = (array) DB::table('tickets')->where('id', $conversation['ticket'])->first();
            unset($attributes['id']);
            DB::table('tickets')->insert([...$attributes, $column => $reference]);
        } else {
            DB::table($tableName)->insert([
                'ticket_id' => $conversation['ticket'],
                'user_id' => DB::table('users')->insertGetId([]),
                $column => $reference,
            ]);
        }
    }
    $originalRows = DB::table($tableName)->orderBy('id')->get();
    Log::spy();

    ticketMigrationRunAll();
    ticketMigrationRunAll();

    expect(DB::table($tableName)->orderBy('id')->get())->toEqual($originalRows)
        ->and(Schema::hasIndex($tableName, [$column]))->toBeTrue()
        ->and(collect(Schema::getForeignKeys($tableName))->contains(fn (array $key): bool => $key['columns'] === [$column]))->toBeFalse()
        ->and(Schema::hasColumn('ticket_attachments', 'created_by'))->toBeTrue()
        ->and(Schema::hasIndex('ticket_activities', ['ticket_id', 'type', 'sender', 'created_at']))->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, "{$tableName}.{$column}")
        && str_contains($message, '2 orphaned references')
        && $context === ['table' => $tableName, 'column' => $column, 'referenced_table' => $referencedTable, 'orphan_count' => 2])->twice();

    // Once the host reconciles its data, the same migration can add the constraint.
    DB::table($tableName)->where($column, 999999)->update([$column => null]);
    ticketMigrationRunAll();
    expect(collect(Schema::getForeignKeys($tableName))->contains(fn (array $key): bool => $key['columns'] === [$column]))->toBeTrue();
})->with([
    'linked ticket' => ['tickets', 'linked_ticket_id', 'tickets'],
    'duplicate ticket' => ['tickets', 'duplicate_of_ticket_id', 'tickets'],
    'seen activity' => ['ticket_user_states', 'last_seen_activity_id', 'ticket_activities'],
    'notified activity' => ['ticket_user_states', 'last_notified_activity_id', 'ticket_activities'],
]);

it('does not copy an orphaned notified pointer into a newly constrained seen pointer', function () {
    HostTicketSchema::create('ticket_notifications');
    Schema::rename('ticket_notifications', 'ticket_user_states');
    Schema::table('ticket_user_states', function (Blueprint $table): void {
        $table->foreignId('last_notified_activity_id')->nullable();
    });
    $conversation = ticketMigrationSeedConversation();
    DB::table('ticket_user_states')->insert([
        'ticket_id' => $conversation['ticket'], 'user_id' => $conversation['user'],
        'last_notified_activity_id' => 999999, 'updated_at' => '2026-01-01 11:00:00',
    ]);
    Log::spy();

    ticketMigrationRunAll(twiceEach: true);

    $state = DB::table('ticket_user_states')->first();
    expect($state->last_notified_activity_id)->toBe(999999)
        ->and($state->last_seen_activity_id)->toBeNull()
        ->and(collect(Schema::getForeignKeys('ticket_user_states'))->contains(fn (array $key): bool => $key['columns'] === ['last_seen_activity_id']))->toBeTrue()
        ->and(collect(Schema::getForeignKeys('ticket_user_states'))->contains(fn (array $key): bool => $key['columns'] === ['last_notified_activity_id']))->toBeFalse();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'ticket_user_states.last_notified_activity_id') && $context['orphan_count'] === 1)->twice();
});
