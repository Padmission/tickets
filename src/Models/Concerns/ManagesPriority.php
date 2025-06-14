<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketPriorityChangedEvent;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\TicketPlugin;

trait ManagesPriority
{
    /**
     * Handle priority transition business logic (called by observer)
     */
    public function handlePriorityTransitionLogic(int $oldPriorityId, int $newPriorityId, ?int $userId = null): void
    {
        // Add activity for priority change
        $this->addActivity(
            ActivityType::PriorityChanged,
            'Priority changed',
            ActivitySender::System,
            $userId,
            [
                'from' => $oldPriorityId,
                'to' => $newPriorityId,
            ]
        );

        event(new TicketPriorityChangedEvent($this, $oldPriorityId, $newPriorityId));
    }

    /**
     * Change the ticket priority
     */
    public function changePriority(int $priorityId, ?int $userId = null): void
    {
        $oldPriorityId = $this->priority_id;

        if ($oldPriorityId === $priorityId) {
            return;
        }

        $this->update(['priority_id' => $priorityId]);

        // Add activity for priority change
        $this->addActivity(
            ActivityType::PriorityChanged,
            'Priority changed',
            ActivitySender::System,
            $userId,
            [
                'from' => $oldPriorityId,
                'to' => $priorityId,
            ]
        );

        event(new TicketPriorityChangedEvent($this, $oldPriorityId, $priorityId));
    }

    /**
     * Escalate the ticket priority
     */
    public function escalate(?int $userId = null): void
    {
        $higherPriority = $this->getHigherPriority();

        if ($higherPriority) {
            $this->changePriority($higherPriority->getKey(), $userId);

            $this->addActivity(
                ActivityType::Escalated,
                'Ticket escalated',
                ActivitySender::System,
                $userId
            );
        }
    }

    /**
     * De-escalate the ticket priority
     */
    public function deEscalate(?int $userId = null): void
    {
        $lowerPriority = $this->getLowerPriority();

        if ($lowerPriority) {
            $this->changePriority($lowerPriority->getKey(), $userId);
        }
    }

    /**
     * Check if the ticket has a specific priority
     */
    public function hasPriority(int $priorityId): bool
    {
        return $this->priority_id === $priorityId;
    }

    /**
     * Get the next higher priority (for escalation)
     */
    protected function getHigherPriority(): ?TicketPriority
    {
        if (! $this->priority) {
            return null;
        }

        return TicketPlugin::resolveModelClass(TicketPriority::class)::query()
            ->where('order', '>', $this->priority->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get the next lower priority (for de-escalation)
     */
    protected function getLowerPriority(): ?TicketPriority
    {
        if (! $this->priority) {
            return null;
        }

        return TicketPlugin::resolveModelClass(TicketPriority::class)::query()
            ->where('order', '<', $this->priority->order)
            ->orderByDesc('order')
            ->first();
    }

    /**
     * Get the priority relationship
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketPriority::class)
        )->withTrashed();
    }
}
