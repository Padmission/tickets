<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Priority;
use Padmission\Tickets\Models\Status;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'subject' => $this->faker->word(),
            'escalation_level' => 'default',
            'submitter_id' => User::factory(),
            'submitter_email' => $this->faker->unique()->safeEmail(),
            'turn' => Turn::User,
            'data' => [],
            'closed_at' => Carbon::now(),

            'status_id' => Status::factory(),
            'priority_id' => Priority::factory(),
            'assignee_id' => User::factory(),
        ];
    }
}
