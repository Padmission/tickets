<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Models\Ticket;

#[UseFactory(TicketFactory::class)]
class HostTicket extends Ticket
{
    protected $table = 'tickets';

    public function status(): BelongsTo
    {
        return parent::status()->withoutGlobalScope('tenant');
    }
}
