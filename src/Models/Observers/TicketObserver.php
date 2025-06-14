<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Contracts\TicketInterface;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketObserver
{
    public function creating(TicketInterface $ticket): void
    {
        $this->addAssignee($ticket);
    }

    public function created(TicketInterface $ticket): void
    {
        event(new TicketCreatedEvent($ticket));
    }

    public function updating(TicketInterface $ticket): void
    {
        $this->handleStatusTransition($ticket);
        $this->handlePriorityTransition($ticket);
    }

    protected function addAssignee(TicketInterface $ticket): void
    {
        // TODO: Make this independent from panel

        // $panel = $ticket->panel;
        //
        // $plugin = TicketPlugin::get($panel);
        // $assignmentStrategy = $plugin->getAssignmentStrategy();
        //
        // if ($assignmentStrategy === null) {
        //     return;
        // }
        //
        // $assignmentStrategy->assign($ticket);
    }

    protected function handlePriorityTransition(TicketInterface $ticket): void
    {
        if ($ticket->isDirty('priority_id')) {
            $ticket->ticketActivities()->create([
                'type' => ActivityType::PriorityChanged,
                'sender' => ActivitySender::System,
                'data' => [
                    'from' => $ticket->getOriginal('priority_id'),
                    'to' => $ticket->priority_id,
                ],
            ]);
        }
    }

    protected function handleStatusTransition(TicketInterface $ticket): void
    {
        if ($ticket->isDirty('status_id')) {
            $ticket->ticketActivities()->create([
                'type' => ActivityType::StatusChanged,
                'sender' => ActivitySender::System,
                'data' => [
                    'from' => $ticket->getOriginal('status_id'),
                    'to' => $ticket->status_id,
                ],
            ]);

            $closedStatus = TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus();
            $isClosedStatus = (int) $ticket->status_id === $closedStatus->getKey();

            // If changing to closed status and ticket isn't already closed, set the closing fields
            if ($isClosedStatus && !$ticket->isClosed && !$ticket->isDirty('closed_at')) {
                // Set the closed_at and closed_by fields directly on the model
                // This will be saved as part of the current update operation
                $ticket->closed_at = now();
                $ticket->closed_by = auth()->id();
                
                // Create the close activity
                $ticket->ticketActivities()->create([
                    'type' => ActivityType::Closed,
                    'sender' => ActivitySender::System,
                    'data' => [
                        'closed_by' => auth()->id(),
                    ],
                ]);
            }
        }
    }

    public function saving(TicketInterface $ticket): void {}

    public function saved(TicketInterface $ticket): void
    {
        if ($ticket->wasChanged('assignee_id')) {
            $old = $ticket->getOriginal('assignee_id');
            $new = $ticket->assignee_id;
            event(new TicketAssignedEvent($ticket));
        }
    }
}
