<?php

namespace Padmission\Tickets\Database\Factories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use Padmission\Tickets\ValueObjects\SubmitterData;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function modelName(): string
    {
        return TicketPlugin::resolveModelClass($this->model);
    }

    public function definition(): array
    {
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);
        $priorityModel = TicketPlugin::resolveModelClass(TicketPriority::class);
        $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);

        return [
            'panel' => 'test', // Default to test panel for tests
            'source_panel' => null,
            'subject' => $this->faker->word(),
            'escalation_level' => 'default',
            'submitter_id' => TicketPlugin::resolveModelClass(Authenticatable::class)::factory(),
            'turn' => Turn::User,
            'data' => [],

            'status_id' => $this->getRandomRecycledModel($statusModel) ?? $statusModel::factory(),
            'disposition_id' => $this->getRandomRecycledModel($dispositionModel) ?? $dispositionModel::factory(),
            'priority_id' => $this->getRandomRecycledModel($priorityModel) ?? $priorityModel::factory(),
            'assignee_id' => $this->getRandomRecycledModel($userModel) ?? $userModel::factory(),
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
        $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);
        $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);

        return $this->state([
            'status_id' => fn (array $attributes): ?TicketStatus => $this->closedStatusFor($attributes),
            'disposition_id' => $this->getRandomRecycledModel($dispositionModel) ?? $dispositionModel::factory(),
            'closed_at' => now(),
            'closed_by' => $this->getRandomRecycledModel($userModel) ?? $userModel::factory(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function closedStatusFor(array $attributes): ?TicketStatus
    {
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);
        $ticket = (new ($this->modelName()))->forceFill([
            'panel' => $attributes['panel'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
        ]);

        return $statusModel::getClosedStatusFor($ticket) ?? $statusModel::getClosedStatus();
    }

    public function open(): static
    {
        $statusModel = TicketPlugin::resolveModelClass(TicketStatus::class);

        return $this->state([
            'status_id' => $statusModel::getOpenStatuses()->first(),
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }
}
