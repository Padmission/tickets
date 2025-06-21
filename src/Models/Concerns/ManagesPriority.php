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
        $this->addTicketActivity(
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

    public function priority(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketPriority::class)
        )->withTrashed();
    }
}
