<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Priority;
use Padmission\Tickets\Models\Status;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'subject' => $this->faker->word(),
            'panel' => 'test',
            'escalation_level' => 'default',
            'submitter_id' => TicketPlugin::resolveModelClass(Authenticatable::class)::factory(),
            'submitter_email' => $this->faker->unique()->safeEmail(),
            'turn' => Turn::User,
            'data' => [],
            'closed_at' => Carbon::now(),

            'status_id' => TicketPlugin::resolveModelClass(Status::class)::factory(),
            'priority_id' => TicketPlugin::resolveModelClass(Priority::class)::factory(),
            'assignee_id' => TicketPlugin::resolveModelClass(Authenticatable::class)::factory(),
        ];
    }
}
