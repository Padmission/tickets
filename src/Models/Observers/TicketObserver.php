<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Events\TicketStatusChangedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        event(new TicketCreatedEvent($ticket));
    }

    public function updating(Ticket $ticket): void
    {
        // Skip status transition logic if close() method is being called explicitly
        if (! $ticket->isExplicitCloseCall()) {
            $this->handleStatusTransition($ticket);
        }

        $this->handlePriorityTransition($ticket);
        $this->handleAssignmentChange($ticket);
    }

    public function saving(Ticket $ticket): void
    {
        // Skip observer closure logic if close() method is being called explicitly
        if ($ticket->isDirty('status_id') && ! $ticket->isExplicitCloseCall()) {
            $this->handleStatusClosureAttributesOnly($ticket);
        }
    }

    public function saved(Ticket $ticket): void
    {
        // Skip observer closure logic if close() method was called explicitly
        if ($ticket->wasChanged('status_id') && ! $ticket->isExplicitCloseCall()) {
            $this->handleStatusClosureActivitiesAndEvents($ticket);
        }
    }

    protected function handleStatusTransition(Ticket $ticket): void
    {
        if ($ticket->isDirty('status_id')) {
            $oldStatusId = $ticket->getOriginal('status_id');
            $newStatusId = $ticket->status_id;

            $ticket->addTicketActivity(
                ActivityType::StatusChanged,
                ActivitySender::System,
                auth()->id(),
                [
                    'from' => $oldStatusId,
                    'to' => $newStatusId,
                ]
            );

            $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();
            $isClosedStatus = $newStatusId === $closedStatus->getKey();

            if ($isClosedStatus && $ticket->isOpen) {
                $ticket->close(closedById: auth()->id());
            }

            event(new TicketStatusChangedEvent($ticket, $oldStatusId, $newStatusId));
        }
    }

    protected function handleStatusClosureAttributesOnly(Ticket $ticket): void
    {
        $newStatusId = $ticket->status_id;
        $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();
        $isClosedStatus = $newStatusId === $closedStatus->getKey();

        // If changing to closed status and ticket isn't already closed
        if ($isClosedStatus && $ticket->closed_at === null) {
            $userId = auth()->id();
            $ticket->closed_by = $userId;
            $ticket->closed_at = now();
        }

        // If changing from closed status to open status
        if (! $isClosedStatus && $ticket->closed_at !== null) {
            $ticket->closed_at = null;
            $ticket->closed_by = null;
            $ticket->disposition_id = null;
        }
    }

    protected function handleStatusClosureActivitiesAndEvents(Ticket $ticket): void
    {
        $oldStatusId = $ticket->getOriginal('status_id');
        $newStatusId = $ticket->status_id;
        $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();
        $isClosedStatus = $newStatusId === $closedStatus->getKey();
        $wasClosedStatus = $oldStatusId === $closedStatus->getKey();

        // If changed to closed status (and wasn't already closed)
        if ($isClosedStatus && ! $wasClosedStatus) {
            $userId = $ticket->closed_by;

            $ticket->addTicketActivity(
                ActivityType::Closed,
                ActivitySender::System,
                $userId,
                ['closed_by' => $userId]
            );

            event(new TicketClosedEvent($ticket));
        }
    }

    protected function handlePriorityTransition(Ticket $ticket): void
    {
        if ($ticket->isDirty('priority_id')) {
            $oldPriorityId = $ticket->getOriginal('priority_id');
            $newPriorityId = $ticket->priority_id;

            $ticket->addTicketActivity(
                ActivityType::PriorityChanged,
                ActivitySender::System,
                auth()->id(),
                [
                    'from' => $oldPriorityId,
                    'to' => $newPriorityId,
                ]
            );
        }
    }

    protected function handleAssignmentChange(Ticket $ticket): void
    {
        if ($ticket->isDirty('assignee_id')) {
            $oldAssigneeId = $ticket->getOriginal('assignee_id');
            $newAssigneeId = $ticket->assignee_id;

            if ($oldAssigneeId !== $newAssigneeId) {
                $ticket->addTicketActivity(
                    ActivityType::AssigneeChanged,
                    ActivitySender::System,
                    auth()->id()
                );

                event(new TicketAssignedEvent($ticket));
            }
        }
    }
}
