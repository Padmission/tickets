<?php

namespace Padmission\Tickets\Notifications;

/**
 * Notification sent when a new ticket is closed.
 *
 * This notification extends AbstractTicketHistoryNotification to provide
 * a consistent email format for ticket creation events.
 *
 * @see AbstractTicketHistoryNotification
 */
class TicketClosedNotification extends AbstractTicketHistoryNotification {
    public function getEmailSubject(): string
    {
        return __('padmission-tickets::notifications.ticket-closed.subject', [
            'subject' => $this->ticket->subject,
            'ticket_id' => $this->ticket->id,
        ]);
    }
}
