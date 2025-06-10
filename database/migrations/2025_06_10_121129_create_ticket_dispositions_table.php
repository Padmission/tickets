<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_dispositions', function (Blueprint $table) {
            $table->id();
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
        Schema::dropIfExists('ticket_dispositions');
    }
};
