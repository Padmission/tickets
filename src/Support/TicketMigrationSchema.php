<?php

namespace Padmission\Tickets\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TicketMigrationSchema
{
    public static function createUserStateTable(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->index('user_id');
            $table->unique(['ticket_id', 'user_id']);
        });
    }

    public static function ensureUserStatePointers(string $tableName): void
    {
        self::ensureNullableForeignId($tableName, 'last_seen_activity_id', 'ticket_activities');
        self::ensureNullableForeignId($tableName, 'last_notified_activity_id', 'ticket_activities');
    }

    public static function ensureNullableForeignId(string $tableName, string $column, string $referencedTable): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->foreignId($column)->nullable();
            });
        }

        // Match by columns: hosts and renamed tables can use different names,
        // and MySQL can already have an implicit index for the foreign key.
        if (! Schema::hasIndex($tableName, [$column])) {
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->index($column);
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $key): bool => $key['columns'] === [$column]);

        if (! $hasForeignKey) {
            // Separate aliases also handle self-references such as linked tickets.
            $orphanCount = DB::table($tableName.' as foreign_key_source')
                ->whereNotNull('foreign_key_source.'.$column)
                ->whereNotExists(function (Builder $query) use ($column, $referencedTable): void {
                    $query->selectRaw('1')
                        ->from($referencedTable.' as foreign_key_target')
                        ->whereColumn('foreign_key_target.id', 'foreign_key_source.'.$column);
                })
                ->count();

            if ($orphanCount > 0) {
                Log::warning("Skipping foreign key for {$tableName}.{$column}: {$orphanCount} orphaned references to {$referencedTable}.id. Existing values are unchanged. Reconcile the references and rerun the migration to add the constraint.", [
                    'table' => $tableName,
                    'column' => $column,
                    'referenced_table' => $referencedTable,
                    'orphan_count' => $orphanCount,
                ]);

                return;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable): void {
                $table->foreign($column)->references('id')->on($referencedTable)->nullOnDelete();
            });
        }
    }
}
