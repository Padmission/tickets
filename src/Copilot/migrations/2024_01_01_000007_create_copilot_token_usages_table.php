<?php

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_token_usages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')
                ->nullable()
                ->constrained('copilot_conversations')
                ->nullOnDelete();
            $table->morphs('participant');
            $table->string('panel_id')->index();
            $table->nullableMorphs('tenant');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->string('model')->nullable();
            $table->string('provider')->nullable();
            $table->date('usage_date')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['participant_type', 'participant_id', 'usage_date'], 'copilot_usage_participant_date');
            $table->index(['panel_id', 'tenant_type', 'tenant_id', 'usage_date'], 'copilot_usage_panel_tenant_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_token_usages');
    }
};
