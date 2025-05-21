<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('escalation_level')->default('default');
            $table->string('subject');
            $table->foreignId('status_id');
            $table->foreignId('priority_id');
            $table->foreignId('assignee_id');
            $table->unsignedInteger('submitter_id')->nullable();
            $table->string('submitter_email')->nullable();
            $table->string('turn');
            $table->json('data')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
