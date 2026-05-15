<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketLastSeen;
use Padmission\Tickets\TicketPlugin;

class TicketLastSeenFactory extends Factory
{
    protected $model = TicketLastSeen::class;

    public function getModel(): string
    {
        return TicketPlugin::resolveModelClass($this->model);
    }

    public function definition(): array
    {
        return [
            'ticket_id' => TicketPlugin::resolveModelClass(Ticket::class)::factory(),
            'user_id' => TicketPlugin::resolveModelClass(Authenticatable::class)::factory(),
            'last_seen_activity_id' => null,
            'last_notified_activity_id' => null,
        ];
    }
}
