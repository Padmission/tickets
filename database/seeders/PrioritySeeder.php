<?php

namespace Padmission\Tickets\Database\Seeders;

use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\Priority;

class PrioritySeeder extends Seeder
{
    public function run(): void
    {
        if (! Priority::query()->exists()) {
            Priority::insert([
                ['display_name' => 'Low', 'panel' => 'admin', 'color' => 'Blue', 'order' => 1],
                ['display_name' => 'Medium', 'panel' => 'admin', 'color' => 'Orange', 'order' => 2],
                ['display_name' => 'High', 'panel' => 'admin', 'color' => 'Red', 'order' => 3],
            ]);
        }
    }
}
