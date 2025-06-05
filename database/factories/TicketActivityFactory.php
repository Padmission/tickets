<?php

namespace Padmission\Tickets\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;

class TicketActivityFactory extends Factory
{
    protected $model = TicketActivity::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->word(),
            'data' => $this->faker->words(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
        ];
    }
}
