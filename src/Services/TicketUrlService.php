<?php

namespace Padmission\Tickets\Services;

use Padmission\Tickets\Models\Ticket;

class TicketUrlService
{
    public function getActionUrl(Ticket $ticket): string
    {
        $data = (array) $ticket->data;
        $url = $data['url'] ?? url('/');

        return $url.'#'.'ticket-'.$ticket->id;
    }
}
