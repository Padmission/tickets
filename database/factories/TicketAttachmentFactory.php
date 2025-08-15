<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\TicketPlugin;

class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    public function getModel(): string
    {
        return TicketPlugin::resolveModelClass($this->model);
    }

    public function definition(): array
    {
        return [
            'ticket_id' => TicketPlugin::resolveModelClass(Ticket::class)::factory(),
            'activity_id' => TicketPlugin::resolveModelClass(TicketActivity::class)::factory(),
            'filename' => $this->faker->word(),
            'filepath' => $this->faker->word(),
            'file_size' => $this->faker->randomNumber(),
            'mime_type' => $this->faker->word(),
        ];
    }
}
