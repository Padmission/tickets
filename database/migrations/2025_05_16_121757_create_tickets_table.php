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

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantKey = config('padmission-tickets.tenancy.foreign_key', 'tenant_id');
                $tenantKeyType = config('padmission-tickets.tenancy.foreign_key_type', 'id');

                match (strtolower($tenantKeyType)) {
                    'ulid' => $table->foreignUlid($tenantKey)->constrained(),
                    'uuid' => $table->foreignUuid($tenantKey)->constrained(),
                    default => $table->foreignId($tenantKey)->constrained(),
                };
            }

            $table->string('escalation_level')->default('default');
            $table->string('subject');
            $table->foreignId('status_id');
            $table->foreignId('priority_id');
            $table->foreignId('disposition_id')->nullable();
            $table->foreignId('assignee_id')->nullable();
            $table->unsignedInteger('submitter_id')->nullable();
            $table->json('submitter_data')->nullable();
            $table->string('turn');
            $table->json('data')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
