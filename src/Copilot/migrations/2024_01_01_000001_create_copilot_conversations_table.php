<?php

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->morphs('participant');
            $table->string('panel_id')->index();
            $table->nullableMorphs('tenant');
            $table->string('title')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['panel_id', 'participant_type', 'participant_id'], 'copilot_conv_panel_participant');
            $table->index(['panel_id', 'tenant_type', 'tenant_id'], 'copilot_conv_panel_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_conversations');
    }
};
