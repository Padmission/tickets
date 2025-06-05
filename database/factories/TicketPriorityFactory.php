<?php

namespace Padmission\Tickets\Database\Factories;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Models\TicketPriority;

class TicketPriorityFactory extends Factory
{
    protected $model = TicketPriority::class;

    public function definition(): array
    {
        return [
            'display_name' => $this->faker->name(),
            'color' => ucfirst($this->faker->randomElement(array_keys(Color::all()))),
            'order' => $this->faker->randomNumber(),
        ];
    }
}
