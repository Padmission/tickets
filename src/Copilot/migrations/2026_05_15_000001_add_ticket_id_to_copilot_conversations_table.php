<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copilot_conversations', function (Blueprint $table) {
            $table->foreignId('ticket_id')
                ->nullable()
                ->after('id')
                ->constrained('tickets')
                ->nullOnDelete();

            $table->unique('ticket_id', 'copilot_conversations_ticket_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('copilot_conversations', function (Blueprint $table) {
            $table->dropUnique('copilot_conversations_ticket_id_unique');
            $table->dropConstrainedForeignId('ticket_id');
        });
    }
};
