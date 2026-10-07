<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Support\TicketMigrationSchema;

return new class extends Migration
{
    public function up(): void
    {
        // Never recreate or rename over a newer host-owned table.
        if (Schema::hasTable('ticket_user_states')) {
            return;
        }

        if (Schema::hasTable('ticket_notifications') && ! Schema::hasTable('ticket_last_seen')) {
            Schema::rename('ticket_notifications', 'ticket_last_seen');
        }

        if (Schema::hasTable('ticket_last_seen')) {
            TicketMigrationSchema::ensureUserStatePointers('ticket_last_seen');
        }
    }

    public function down(): void
    {
        Schema::table('ticket_last_seen', function (Blueprint $table) {
            $table->dropForeign(['last_seen_activity_id']);
            $table->dropForeign(['last_notified_activity_id']);
            $table->dropColumn(['last_seen_activity_id', 'last_notified_activity_id']);
        });

        Schema::rename('ticket_last_seen', 'ticket_notifications');
    }
};
