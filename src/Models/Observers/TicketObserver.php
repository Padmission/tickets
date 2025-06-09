<?php

namespace Padmission\Tickets\Models\Observers;

use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\ActivityVisibility;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketObserver
{
    public function creating(Ticket $ticket): void
    {
        $this->addAssignee($ticket);
    }

    public function created(Ticket $ticket): void
    {
        $this->sendTicketCreatedNotification($ticket);
    }

    public function updating(Ticket $ticket): void
    {
        $this->handleStatusTransition($ticket);
        $this->handlePriorityTransition($ticket);
    }

    protected function addAssignee(Ticket $ticket): void
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

    protected function sendTicketCreatedNotification(Ticket $ticket): void
    {
        // TODO: Make this independent from panel

        // $panel = $ticket->panel;
        //
        // $plugin = TicketPlugin::get($panel);
        // $notificationStrategy = $plugin->getNotificationStrategy();
        //
        // if ($notificationStrategy === null) {
        //     return;
        // }
        //
        // $notificationStrategy->notify($ticket);
    }

    protected function handlePriorityTransition(Ticket $ticket): void
    {
        if ($ticket->isDirty('priority_id')) {
            $ticket->ticketActivities()->create([
                'visibility' => ActivityVisibility::Private,
                'type' => ActivityType::PriorityChanged,
                'sender' => ActivitySender::System,
                'data' => [
                    'from' => $ticket->getOriginal('priority_id'),
                    'to' => $ticket->priority_id,
                ],
            ]);
        }
    }

    protected function handleStatusTransition(Ticket $ticket): void
    {
        if ($ticket->isDirty('status_id')) {
            $ticket->ticketActivities()->create([
                'visibility' => ActivityVisibility::Private,
                'type' => ActivityType::StatusChanged,
                'sender' => ActivitySender::System,
                'data' => [
                    'from' => $ticket->getOriginal('status_id'),
                    'to' => $ticket->status_id,
                ],
            ]);

            $isClosedStatus = (int) $ticket->status_id === TicketPlugin::resolveModelClass(TicketStatus::class)::getClosedStatus()->getKey();

            if ($isClosedStatus) {
                $ticket->close(auth()->id());
            }
        }
    }
}
