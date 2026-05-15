<?php

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')
                ->constrained('copilot_conversations')
                ->cascadeOnDelete();
            $table->string('role');
            $table->longText('content')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->nullableMorphs('tenant');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_messages');
    }
};
