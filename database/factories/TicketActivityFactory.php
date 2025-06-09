<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\ActivityVisibility;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\TicketPlugin;

class TicketActivityFactory extends Factory
{
    protected $model = TicketActivity::class;

    public function definition(): array
    {
        return [
            'ticket_id' => TicketPlugin::resolveModelClass(Ticket::class)::factory(),

            'sender' => ActivitySender::System,
            'type' => ActivityType::Message,
            'visibility' => ActivityVisibility::Public,

            'content' => $this->faker->words(),
        ];
    }
}
