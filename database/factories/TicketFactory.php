<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'subject' => $this->faker->word(),
            'panel' => 'test',
            'escalation_level' => 'default',
            'submitter_id' => config('padmission-tickets.models.user')::factory(),
            'submitter_email' => $this->faker->unique()->safeEmail(),
            'turn' => Turn::User,
            'data' => [],
            'closed_at' => Carbon::now(),

            'status_id' => config('padmission-tickets.models.status')::factory(),
            'priority_id' => config('padmission-tickets.models.priority')::factory(),
            'assignee_id' => config('padmission-tickets.models.user')::factory(),
        ];
    }
}
