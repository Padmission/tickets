<?php

namespace Padmission\Tickets\Database\Seeders;

use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\Status;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        if (! Status::query()->exists()) {
            Status::insert([
                ['display_name' => 'Open', 'panel' => 'admin', 'color' => 'Gray', 'order' => 1],
                ['display_name' => 'In Progress', 'panel' => 'admin', 'color' => 'Blue', 'order' => 2],
                ['display_name' => 'Closed', 'panel' => 'admin', 'color' => 'Green', 'order' => 3],
            ]);
        }
    }
}
