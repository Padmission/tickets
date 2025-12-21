<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ticket_notifications', 'ticket_last_seen');

        Schema::table('ticket_last_seen', function (Blueprint $table) {
            $table->foreignId('last_seen_activity_id')
                ->nullable()
                ->after('user_id')
                ->constrained('ticket_activities')
                ->nullOnDelete();

            $table->foreignId('last_notified_activity_id')
                ->nullable()
                ->after('last_seen_activity_id')
                ->constrained('ticket_activities')
                ->nullOnDelete();

            $table->index('last_seen_activity_id');
            $table->index('last_notified_activity_id');
        });
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
