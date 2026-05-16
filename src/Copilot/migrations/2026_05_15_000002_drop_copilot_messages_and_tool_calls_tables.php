<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('copilot_tool_calls');
        Schema::dropIfExists('copilot_messages');
    }

    public function down(): void
    {
        // The v3 data model stores chat turns in ticket_activities.
    }
};
