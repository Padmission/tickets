<?php

namespace Padmission\Tickets\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

trait CanBeClosed
{
    /**
     * Temporary flag to indicate explicit close() call
     */
    private bool $isExplicitCloseCall = false;

    /**
     * Check if this is an explicit close() call
     */
    public function isExplicitCloseCall(): bool
    {
        return $this->isExplicitCloseCall;
    }

    /**
     * Close the ticket
     */
    public function close(?int $dispositionId = null, ?int $closedBy = null): void
    {
        // Use direct attribute check instead of method to avoid conflicts
        if ($this->closed_at !== null) {
            return;
        }

        $closedBy ??= auth()->id();

        // Get the closed status
        $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();

        // Store the original status before changing it
        $originalStatusId = $this->status_id;
        $newStatusId = $closedStatus->getKey();

        // Set flag to indicate this is an explicit close() call
        $this->isExplicitCloseCall = true;

        // Use direct attribute assignment to avoid observer conflicts
        $this->disposition_id = $dispositionId;
        $this->closed_by = $closedBy;
        $this->closed_at = now();
        $this->status_id = $newStatusId;

        // Save the model
        $this->save();

        // Clear the flag
        $this->isExplicitCloseCall = false;

        // Create status change activity if status actually changed
        if ($originalStatusId !== $newStatusId) {
            $this->addActivity(
                ActivityType::StatusChanged,
                'Status changed',
                ActivitySender::System,
                $closedBy,
                [
                    'from' => $originalStatusId,
                    'to' => $newStatusId,
                ]
            );
        }

        // Create closed activity
        $this->addActivity(
            ActivityType::Closed,
            'Ticket closed',
            ActivitySender::System,
            $closedBy,
            ['closed_by' => $closedBy, 'disposition_id' => $dispositionId]
        );

        event(new TicketClosedEvent($this));
    }

    /* Attributes */

    /**
     * @return Attribute<bool, never>
     */
    protected function isClosed(): Attribute
    {
        return Attribute::get(fn () => $this->closed_at !== null);
    }

    public function isOpen(): Attribute
    {
        return Attribute::get(fn () => $this->closed_at === null);
    }

    /**
     * Get the disposition relationship
     */
    public function disposition(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketDisposition::class)
        )->withTrashed();
    }
}
