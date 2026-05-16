<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

return new class extends Migration
{
    public function up(): void
    {
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);
        $tenantKey = null;

        if (config('padmission-tickets.tenancy.enabled', false)) {
            $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
            $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';
        }

        $pairColumns = array_filter(['panel', $tenantKey]);

        DB::table('ticket_statuses')
            ->select($pairColumns)
            ->distinct()
            ->orderBy('panel')
            ->get()
            ->each(function (object $pair) use ($statusModel, $tenantKey): void {
                $attributes = [
                    'panel' => $pair->panel,
                    'seed_key' => 'ai_in_progress',
                ];

                if ($tenantKey) {
                    $attributes[$tenantKey] = $pair->{$tenantKey};
                }

                $maxOrder = (int) DB::table('ticket_statuses')
                    ->where('panel', $pair->panel)
                    ->when($tenantKey, fn ($query) => $query->where($tenantKey, $pair->{$tenantKey}))
                    ->max('order');

                /** @var TicketStatus $status */
                $status = $statusModel::withTrashed()
                    ->withoutGlobalScope(CurrentPanelScope::class)
                    ->updateOrCreate($attributes, [
                        'display_name' => 'AI in progress',
                        'color' => 'Gray',
                        'order' => max(2, $maxOrder),
                    ]);

                if ($status->trashed()) {
                    $status->restore();
                }
            });
    }

    public function down(): void
    {
        DB::table('ticket_statuses')
            ->where('seed_key', 'ai_in_progress')
            ->delete();
    }
};
