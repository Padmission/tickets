<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $tenantModel = new $tenantModelClass;
                $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';
                $tenantKeyType = $tenantModel->getKeyType();

                match (strtolower($tenantKeyType)) {
                    'string' => $table->foreignUlid($tenantKey)->constrained(),
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
        Schema::dropIfExists('ticket_statuses');
    }
};
