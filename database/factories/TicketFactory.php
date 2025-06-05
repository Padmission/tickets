<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\ValueObjects\SubmitterData;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'subject' => $this->faker->word(),
            'escalation_level' => 'default',
            'submitter_id' => TicketPlugin::resolveModelClass(Authenticatable::class)::factory(),
            'turn' => Turn::User,
            'data' => [],

            'status_id' => TicketPlugin::resolveModelClass(TicketStatus::class)::factory(),
            'priority_id' => TicketPlugin::resolveModelClass(TicketPriority::class)::factory(),
            'assignee_id' => TicketPlugin::resolveModelClass(Authenticatable::class)::factory(),
        ];
    }

    public function withSubmitterData()
    {
        return $this->state([
            'submitter_id' => null,
            'submitter_data' => new SubmitterData(
                $this->faker->name(),
                $this->faker->unique()->safeEmail()
            ),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status_id' => TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus(),
            'closed_at' => now(),
            'closed_by' => TicketPlugin::resolveModelClass(Authenticatable::class)::factory(),
        ]);
    }
}
