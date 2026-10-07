<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Support\TicketMigrationSchema;

return new class extends Migration
{
    public function up(): void
    {
        $renamedFromLegacyTable = false;

        if (Schema::hasTable('ticket_last_seen') && ! Schema::hasTable('ticket_user_states')) {
            Schema::rename('ticket_last_seen', 'ticket_user_states');

            $renamedFromLegacyTable = true;
        }

        if (Schema::hasTable('ticket_notifications') && ! Schema::hasTable('ticket_user_states')) {
            Schema::rename('ticket_notifications', 'ticket_user_states');

            $renamedFromLegacyTable = true;
        }

        TicketMigrationSchema::createUserStateTable('ticket_user_states');

        $hasLastSeenActivityId = Schema::hasColumn('ticket_user_states', 'last_seen_activity_id');
        $hasLastNotifiedActivityId = Schema::hasColumn('ticket_user_states', 'last_notified_activity_id');

        TicketMigrationSchema::ensureUserStatePointers('ticket_user_states');

        // The v2 migration added both pointer columns to the legacy table without
        // backfilling them, so the backfill must run whenever this migration renames
        // a legacy table — not only when it creates the columns itself. Every update
        // below touches NULL pointers only, keeping the backfill idempotent and
        // preserving pointers that already carry real values.
        if (! $renamedFromLegacyTable && $hasLastSeenActivityId && $hasLastNotifiedActivityId) {
            return;
        }

        DB::table('ticket_user_states')
            ->whereNull('last_notified_activity_id')
            ->update([
                'last_notified_activity_id' => DB::raw('(
                    select max(ticket_activities.id)
                    from ticket_activities
                    where ticket_activities.ticket_id = ticket_user_states.ticket_id
                        and ticket_activities.type != \'turn-changed\'
                        and ticket_activities.created_at <= ticket_user_states.updated_at
                )'),
            ]);

        DB::table('ticket_user_states')
            ->whereNull('last_seen_activity_id')
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('tickets')
                    ->whereColumn('tickets.id', 'ticket_user_states.ticket_id')
                    ->whereNotNull('tickets.closed_at');
            })
            ->update([
                'last_seen_activity_id' => DB::raw('(
                    select max(ticket_activities.id)
                    from ticket_activities
                    where ticket_activities.ticket_id = ticket_user_states.ticket_id
                )'),
            ]);

        DB::table('ticket_user_states')
            ->whereNull('last_seen_activity_id')
            ->whereNotNull('last_notified_activity_id')
            ->update([
                'last_seen_activity_id' => DB::raw('last_notified_activity_id'),
            ]);
    }
};
