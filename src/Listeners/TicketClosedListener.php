<?php

namespace Padmission\Tickets\Listeners;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Mockery\Matcher\Not;
use Padmission\Tickets\Events\TicketActivity;
use Padmission\Tickets\Events\TicketClosed;
use Padmission\Tickets\Jobs\DebouncedNotificationJob;
use Padmission\Tickets\Jobs\NotificationJob;

class TicketClosedListener extends AbstractTicketListener
{
}
