<?php

namespace Padmission\Tickets\Models\Contracts;

interface HasTicketDisplayName
{
    /**
     * Get the display name for ticket activities and notifications
     */
    public function getNameForTickets(): string;
}
