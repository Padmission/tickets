<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();

            if (Padmission\Tickets\Support\Utils::isTenantEnabled()) {
                $tenantForeignKey = config('padmission-tickets.tenant.tenant_foreign_key', 'tenant_id');
                $table->foreignId($tenantForeignKey)->constrained()->cascadeOnDelete();
            }

            $table->string('panel');
            $table->string('display_name');
            $table->string('color');
            $table->unsignedInteger('order')->default(99);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_priorities');
    }
};
