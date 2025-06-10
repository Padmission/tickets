<?php

namespace Padmission\Tickets\Database\Factories;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Models\TicketDisposition;

class TicketDispositionFactory extends Factory
{
    protected $model = TicketDisposition::class;

    public function definition(): array
    {
        return [
            'display_name' => $this->faker->words(2, true),
            'color' => ucfirst($this->faker->randomElement(array_keys(Color::all()))),
            'order' => $this->faker->randomNumber(),
        ];
    }
}
