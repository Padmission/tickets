<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('ticket_activities', ['ticket_id', 'type', 'sender', 'created_at'])) {
            return;
        }

        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->index(['ticket_id', 'type', 'sender', 'created_at'], 'ticket_activities_requester_age_index');
        });
    }
};
