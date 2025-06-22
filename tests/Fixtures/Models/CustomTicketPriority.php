<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Padmission\Tickets\Models\TicketPriority;

class CustomTicketPriority extends TicketPriority
{
    protected $table = 'ticket_priorities';
}
