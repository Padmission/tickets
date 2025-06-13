<?php

namespace Padmission\Tickets\Notifications;

/**
 * Notification sent when a new ticket is assigned.
 *
 * This notification extends AbstractTicketHistoryNotification to provide
 * a consistent email format for ticket creation events.
 *
 * @see AbstractTicketHistoryNotification
 */
class TicketAssignedNotification extends AbstractTicketHistoryNotification
{
    public function getEmailSubject(): string
    {
        return __('padmission-tickets::notifications.ticket-assigned.subject', [
            'subject' => $this->ticket->subject,
            'ticket_id' => $this->ticket->id,
        ]);
    }
}
