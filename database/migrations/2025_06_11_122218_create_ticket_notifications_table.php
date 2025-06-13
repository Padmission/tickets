<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_notifications', function (Blueprint $table) {
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

            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['ticket_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_notifications');
    }
};
