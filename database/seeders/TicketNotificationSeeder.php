<?php

namespace Padmission\Tickets\Database\Seeders;

use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\TicketNotification;
use Padmission\Tickets\TicketPlugin;

class TicketNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $model = TicketPlugin::resolveModelClass(TicketNotification::class);

        if ($model::count() > 0) {
            return;
        }

        // Create some sample ticket notifications
        $model::factory()
            ->count(5)
            ->create();
    }
}
