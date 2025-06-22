<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Padmission\Tickets\Models\Ticket;

class CustomTicket extends Ticket
{
    protected $table = 'tickets';
}
