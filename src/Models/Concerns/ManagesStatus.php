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
    public function handleStatusTransitionLogic(int $oldStatusId, int $newStatusId, ?int $userId = null): void
    {
        $this->addTicketActivity(
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

    protected function handleStatusTransition(int $newStatusId, ?int $userId = null): void
    {
        $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();
        $isClosedStatus = $newStatusId === $closedStatus->getKey();

        if ($isClosedStatus && $this->isOpen) {
            $this->close(closedById: $userId);
        }
    }

    public function hasStatus(int $statusId): bool
    {
        return $this->status_id === $statusId;
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketStatus::class)
        )->withTrashed();
    }
}
