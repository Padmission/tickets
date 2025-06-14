<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketStatusChangedEvent;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

trait ManagesStatus
{
    /**
     * Handle status transition business logic (called by observer)
     */
    public function handleStatusTransitionLogic(int $oldStatusId, int $newStatusId, ?int $userId = null): void
    {
        // Add activity for status change
        $this->addActivity(
            ActivityType::StatusChanged,
            'Status changed',
            ActivitySender::System,
            $userId,
            [
                'from' => $oldStatusId,
                'to' => $newStatusId,
            ]
        );

        // Check if this transition closes the ticket
        $this->handleStatusTransition($newStatusId, $userId);

        event(new TicketStatusChangedEvent($this, $oldStatusId, $newStatusId));
    }

    /**
     * Transition the ticket to a new status
     */
    public function transitionToStatus(int $statusId, ?int $userId = null): void
    {
        $oldStatusId = $this->status_id;

        if ($oldStatusId === $statusId) {
            return;
        }

        $this->update(['status_id' => $statusId]);

        // Add activity for status change
        $this->addActivity(
            ActivityType::StatusChanged,
            'Status changed',
            ActivitySender::System,
            $userId,
            [
                'from' => $oldStatusId,
                'to' => $statusId,
            ]
        );

        // Check if this transition closes the ticket
        $this->handleStatusTransition($statusId, $userId);

        event(new TicketStatusChangedEvent($this, $oldStatusId, $statusId));
    }

    /**
     * Handle automatic actions based on status transition
     */
    protected function handleStatusTransition(int $newStatusId, ?int $userId = null): void
    {
        $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();
        $isClosedStatus = $newStatusId === $closedStatus->getKey();

        // If changing to closed status and ticket isn't already closed
        if ($isClosedStatus && $this->isOpen()) {
            $this->close(closedBy: $userId);
        }
    }

    /**
     * Check if the ticket has a specific status
     */
    public function hasStatus(int $statusId): bool
    {
        return $this->status_id === $statusId;
    }

    /**
     * Check if the ticket status is in a list of statuses
     */
    public function hasStatusIn(array $statusIds): bool
    {
        return in_array($this->status_id, $statusIds);
    }

    /**
     * Get the status relationship
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketStatus::class)
        )->withTrashed();
    }
}
