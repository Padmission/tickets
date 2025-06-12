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

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantKey = config('padmission-tickets.tenancy.foreign_key', 'tenant_id');
                $tenantKeyType = config('padmission-tickets.tenancy.foreign_key_type', 'id');

                match (strtolower($tenantKeyType)) {
                    'ulid' => $table->foreignUlid($tenantKey)->constrained(),
                    'uuid' => $table->foreignUuid($tenantKey)->constrained(),
                    default => $table->foreignId($tenantKey)->constrained(),
                };
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
