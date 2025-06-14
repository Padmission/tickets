<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\TicketPlugin;

trait CanBeAssigned
{
    /**
     * Handle assignment transition business logic (called by observer)
     */
    public function handleAssignmentTransitionLogic(?int $oldAssigneeId, ?int $newAssigneeId, ?int $userId = null): void
    {
        if ($oldAssigneeId !== $newAssigneeId) {
            $this->addActivity(
                ActivityType::AssigneeChanged,
                $newAssigneeId ? "Assigned to user {$newAssigneeId}" : 'Unassigned',
                ActivitySender::System,
                $userId
            );

            event(new TicketAssignedEvent($this));
        }
    }

    /**
     * Assign the ticket to a user
     */
    public function assignTo(?int $userId): void
    {
        $oldAssigneeId = $this->assignee_id;

        $this->update(['assignee_id' => $userId]);

        if ($oldAssigneeId !== $userId) {
            $this->addActivity(
                ActivityType::AssigneeChanged,
                $userId ? "Assigned to user {$userId}" : 'Unassigned',
                ActivitySender::System
            );

            event(new TicketAssignedEvent($this));
        }
    }

    /**
     * Unassign the ticket
     */
    public function unassign(): void
    {
        $this->assignTo(null);
    }

    /**
     * Check if the ticket is assigned
     */
    public function isAssigned(): bool
    {
        return $this->assignee_id !== null;
    }

    /**
     * Check if the ticket is assigned to a specific user
     */
    public function isAssignedTo(int $userId): bool
    {
        return $this->assignee_id === $userId;
    }

    /**
     * Get the assignee relationship
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'assignee_id'
        );
    }
}
