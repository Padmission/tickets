<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->string('seed_key')->nullable()->after('panel');
        });

        DB::table('ticket_statuses')
            ->whereIn('display_name', ['Open', 'In Progress', 'Closed', 'AI in progress'])
            ->orderBy('id')
            ->each(function (object $status): void {
                DB::table('ticket_statuses')
                    ->where('id', $status->id)
                    ->update(['seed_key' => Str::slug($status->display_name, '_')]);
            });

        Schema::table('ticket_statuses', function (Blueprint $table) {
            $columns = ['panel'];

            if (config('padmission-tickets.tenancy.enabled', false)) {
                $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
                $columns[] = Str::snake(class_basename($tenantModelClass)).'_id';
            }

            $columns[] = 'seed_key';

            $table->unique($columns, 'ticket_statuses_seed_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->dropUnique('ticket_statuses_seed_key_unique');
            $table->dropColumn('seed_key');
        });
    }
};
