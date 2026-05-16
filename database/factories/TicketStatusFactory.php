<?php

namespace Padmission\Tickets\Database\Factories;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketStatusFactory extends Factory
{
    protected $model = TicketStatus::class;

    public function getModel(): string
    {
        return TicketPlugin::resolveModelClass($this->model);
    }

    public function definition(): array
    {
        return [
            'display_name' => $this->faker->name(),
            'color' => ucfirst($this->faker->randomElement(array_keys(Color::all()))),
            'order' => $this->faker->randomNumber(),
            'panel' => 'test',
            'seed_key' => null,
        ];
    }
}
