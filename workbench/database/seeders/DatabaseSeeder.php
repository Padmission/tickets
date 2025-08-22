<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Database\Seeders\TicketDispositionSeeder;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketSeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'dev@padmission.com')->exists()) {
            return;
        }

        User::firstOrCreate([
            'name' => 'Padmission Dev',
            'email' => 'dev@padmission.com',
            'password' => bcrypt('password'),
        ]);

        (new TicketStatusSeeder)->run();
        (new TicketPrioritySeeder)->run();
        (new TicketDispositionSeeder())->run();
        (new TicketSeeder)->run();
    }
}
