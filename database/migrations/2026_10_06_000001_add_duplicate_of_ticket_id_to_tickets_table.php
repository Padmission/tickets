<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'duplicate_of_ticket_id')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('duplicate_of_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
        });
    }
};
