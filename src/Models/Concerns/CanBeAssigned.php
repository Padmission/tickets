<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;
use Padmission\Tickets\TicketPlugin;

trait CanBeAssigned
{
    /**
     * Get display name for a user in ticket activities
     */
    protected function getUserDisplayName(?int $userId): string
    {
        if (! $userId) {
            return 'unassigned';
        }

        $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);
        $user = $userModel::find($userId);

        if (! $user) {
            return "user {$userId}";
        }

        // Check if user implements the interface for custom display names
        if ($user instanceof HasTicketDisplayName) {
            return $user->getNameForTickets();
        }

        // Fallback to common name attributes
        if (isset($user->name)) {
            return $user->name;
        }

        if (isset($user->email)) {
            return $user->email;
        }

        // Last resort fallback
        return "user {$userId}";
    }

    /**
     * Handle assignment transition business logic (called by observer)
     */
    public function handleAssignmentTransitionLogic(?int $oldAssigneeId, ?int $newAssigneeId, ?int $userId = null): void
    {
        if ($oldAssigneeId !== $newAssigneeId) {
            $assignmentMessage = $newAssigneeId
                ? "Assigned to {$this->getUserDisplayName($newAssigneeId)}"
                : 'Unassigned';

            $this->addActivity(
                ActivityType::AssigneeChanged,
                $assignmentMessage,
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
            $assignmentMessage = $userId
                ? "Assigned to {$this->getUserDisplayName($userId)}"
                : 'Unassigned';

            $this->addActivity(
                ActivityType::AssigneeChanged,
                $assignmentMessage,
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
