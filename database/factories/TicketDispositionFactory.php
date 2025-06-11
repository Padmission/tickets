<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Models\TicketDisposition;

class TicketDispositionFactory extends Factory
{
    protected $model = TicketDisposition::class;

    public function definition(): array
    {
        return [
            'display_name' => $this->faker->words(2, true),
            'order' => $this->faker->randomNumber(),
        ];
    }
}
