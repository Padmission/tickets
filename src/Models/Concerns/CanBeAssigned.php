<?php

namespace Padmission\Tickets\Models\Concerns;

use Filament\Models\Contracts\HasName;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;
use Padmission\Tickets\TicketPlugin;

trait CanBeAssigned
{
    protected function getUserDisplayName(?int $userId): string
    {
        if (! $userId) {
            return __('padmission-tickets::activities.user_display.unassigned');
        }

        $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);
        $user = $userModel::find($userId);

        if (! $user) {
            return __('padmission-tickets::activities.user_display.user_not_found', ['id' => $userId]);
        }

        if ($user instanceof HasTicketDisplayName) {
            return $user->getNameForTickets();
        }

        if ($user instanceof HasName) {
            return $user->getFilamentName();
        }

        // Fallback to common name attributes
        if (isset($user->name)) {
            return $user->name;
        }

        if (isset($user->email)) {
            return $user->email;
        }

        // Last resort fallback
        return __('padmission-tickets::activities.user_display.user_not_found', ['id' => $userId]);
    }

    /**
     * Handle assignment transition business logic (called by observer)
     */
    public function handleAssignmentTransitionLogic(?int $oldAssigneeId, ?int $newAssigneeId, ?int $userId = null): void
    {
        if ($oldAssigneeId !== $newAssigneeId) {
            $assignmentMessage = $newAssigneeId
                ? __('padmission-tickets::activities.assigned_to', ['name' => $this->getUserDisplayName($newAssigneeId)])
                : __('padmission-tickets::activities.unassigned');

            $this->addTicketActivity(
                ActivityType::AssigneeChanged,
                $assignmentMessage,
                ActivitySender::System,
                $userId
            );

            event(new TicketAssignedEvent($this));
        }
    }

    public function isAssigned(): bool
    {
        return $this->assignee_id !== null;
    }

    public function isAssignedTo(int $userId): bool
    {
        return $this->assignee_id === $userId;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'assignee_id'
        );
    }
}
