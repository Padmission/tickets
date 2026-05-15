<?php

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_agent_memories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('participant_type', 150);
            $table->unsignedBigInteger('participant_id');
            $table->string('panel_id', 100)->index();
            $table->string('tenant_type', 150)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key', 150)->index();
            $table->text('value');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['participant_type', 'participant_id']);
            $table->index(['tenant_type', 'tenant_id']);
            $table->unique(
                ['participant_type', 'participant_id', 'panel_id', 'tenant_type', 'tenant_id', 'key'],
                'copilot_memory_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_agent_memories');
    }
};
