<?php

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_rate_limits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('panel_id', 100)->index();
            $table->string('tenant_type', 150)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('participant_type', 150)->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->unsignedInteger('max_messages_per_hour')->default(60);
            $table->unsignedInteger('max_messages_per_day')->default(500);
            $table->unsignedInteger('max_tokens_per_hour')->default(100000);
            $table->unsignedInteger('max_tokens_per_day')->default(1000000);
            $table->boolean('is_blocked')->default(false);
            $table->boolean('copilot_enabled')->default(true);
            $table->timestamp('blocked_until')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_type', 'tenant_id']);
            $table->index(['participant_type', 'participant_id']);
            $table->index(['panel_id', 'tenant_type', 'tenant_id'], 'copilot_rate_panel_tenant');
            $table->unique(
                ['panel_id', 'tenant_type', 'tenant_id', 'participant_type', 'participant_id'],
                'copilot_rate_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_rate_limits');
    }
};
