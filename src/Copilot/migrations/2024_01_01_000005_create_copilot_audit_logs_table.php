<?php

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')
                ->nullable()
                ->constrained('copilot_conversations')
                ->nullOnDelete();
            $table->morphs('participant');
            $table->string('panel_id')->index();
            $table->nullableMorphs('tenant');
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->string('record_key')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['panel_id', 'action']);
            $table->index(['participant_type', 'participant_id', 'created_at'], 'copilot_audit_participant_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_audit_logs');
    }
};
