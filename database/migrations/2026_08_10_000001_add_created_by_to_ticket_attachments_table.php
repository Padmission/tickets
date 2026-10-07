<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_attachments', 'created_by')) {
            Schema::table('ticket_attachments', function (Blueprint $table): void {
                $table->unsignedBigInteger('created_by')->nullable()->after('activity_id');
            });
        }

        if (! Schema::hasIndex('ticket_attachments', ['created_by'])) {
            Schema::table('ticket_attachments', function (Blueprint $table): void {
                $table->index('created_by');
            });
        }
    }
};
