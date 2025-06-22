<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Padmission\Tickets\Models\TicketNotification;

class CustomTicketNotification extends TicketNotification
{
    protected $table = 'ticket_notifications';
}
