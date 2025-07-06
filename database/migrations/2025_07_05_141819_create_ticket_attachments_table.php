<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('ticket_activities')->cascadeOnDelete();
            $table->string('filename');
            $table->string('filepath');
            $table->string('preview_filepath')->nullable();
            $table->string('mime_type');
            $table->timestamps();

            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
