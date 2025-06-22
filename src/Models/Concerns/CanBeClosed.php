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
    private bool $isExplicitCloseCall = false;

    public function isExplicitCloseCall(): bool
    {
        return $this->isExplicitCloseCall;
    }

    public function close(?int $dispositionId = null, ?int $closedById = null): void
    {
        if ($this->closed_at !== null) {
            return;
        }

        $closedById ??= auth()->id();

        $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();

        $originalStatusId = $this->status_id;
        $newStatusId = $closedStatus->getKey();

        $this->isExplicitCloseCall = true;

        $this->disposition_id = $dispositionId;
        $this->closed_by = $closedById;
        $this->closed_at = now();
        $this->status_id = $newStatusId;

        $this->save();

        // Clear the flag
        $this->isExplicitCloseCall = false;

        if ($originalStatusId !== $newStatusId) {
            $this->addTicketActivity(
                ActivityType::StatusChanged,
                ActivitySender::System,
                $closedById,
                [
                    'from' => $originalStatusId,
                    'to' => $newStatusId,
                ]
            );
        }

        $this->addTicketActivity(
            ActivityType::Closed,
            ActivitySender::System,
            $closedById,
            ['closed_by' => $closedById, 'disposition_id' => $dispositionId]
        );

        event(new TicketClosedEvent($this));
    }

    /* Attributes */

    protected function isClosed(): Attribute
    {
        return Attribute::get(fn () => $this->closed_at !== null);
    }

    public function isOpen(): Attribute
    {
        return Attribute::get(fn () => $this->closed_at === null);
    }

    public function disposition(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketDisposition::class)
        )->withTrashed();
    }
}
