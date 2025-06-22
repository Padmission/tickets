<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Padmission\Tickets\Models\TicketStatus;

class CustomTicketStatus extends TicketStatus
{
    protected $table = 'ticket_statuses';
}
