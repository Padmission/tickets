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

            if (Padmission\Tickets\Support\Utils::isTenantEnabled()) {
                $tenantForeignKey = config('padmission-tickets.tenant.tenant_foreign_key', 'tenant_id');
                $table->foreignId($tenantForeignKey)->constrained()->cascadeOnDelete();
            }

            $table->string('escalation_level')->default('default');
            $table->string('subject');
            $table->foreignId('status_id');
            $table->foreignId('priority_id');
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
