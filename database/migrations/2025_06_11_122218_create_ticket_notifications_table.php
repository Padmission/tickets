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
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';
                $traits = class_uses_recursive($tenantModelClass);

                match (true) {
                    in_array(HasUlids::class, $traits) => $table->foreignUlid($tenantKey)->constrained(),
                    in_array(HasUuids::class, $traits) => $table->foreignUuid($tenantKey)->constrained(),
                    default => $table->foreignId($tenantKey)->constrained(),
                };
            }

            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_notifications');
    }
};
