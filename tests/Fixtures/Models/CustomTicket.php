<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Models\Ticket;

#[UseFactory(TicketFactory::class)]
class CustomTicket extends Ticket
{
    protected $table = 'tickets';
}
