<?php

namespace Padmission\Tickets\Listeners;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Padmission\Tickets\Events\TicketActivity;
use Padmission\Tickets\Events\TicketCreated;
use Padmission\Tickets\Jobs\NotificationJob;

class TicketCreatedListener extends AbstractTicketListener
{
}
