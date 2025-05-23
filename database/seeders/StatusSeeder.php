<?php

namespace Padmission\Tickets\Database\Seeders;

use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\Status;

class StatusSeeder extends Seeder
{
    public function run(string $panel = 'admin'): void
    {
        if (! Status::query()->exists()) {
            Status::insert([
                ['display_name' => 'Open', 'panel' => $panel, 'color' => 'Gray', 'order' => 1],
                ['display_name' => 'In Progress', 'panel' => $panel, 'color' => 'Blue', 'order' => 2],
                ['display_name' => 'Closed', 'panel' => $panel, 'color' => 'Green', 'order' => 3],
            ]);
        }
    }
}
