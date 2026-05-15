<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Padmission\Tickets\Models\TicketLastSeen;

class CustomTicketLastSeen extends TicketLastSeen
{
    protected $table = 'ticket_last_seen';
}
